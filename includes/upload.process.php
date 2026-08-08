<?php
/**
 *  Call the required system files
 */
require_once '../bootstrap.php';

/**
 * If there is no valid session/user block the upload of files
 */
if ( !user_is_logged_in() ) {
	exit;
}

// Release session lock to prevent blocking keep-alive AJAX requests during upload
// All session data has been read and CURRENT_USER_* constants are already set
session_write_close();

function dieWithError($message = null, $code = 400)
{
    header('Content-Type: application/json');
    $response = [
        'OK' => 0,
        'error' => [
            'code' => $code,
            'message' => $message,
            // The refusals above happen before a chunk is even looked at, so
            // there is not always a filename to report back
            'filename' => isset($_POST['name']) ? $_POST['name'] : null
        ]
    ];

    echo json_encode($response);
    http_response_code($code);
    exit;
}

/**
 * Being logged in is not enough to upload. This has to be checked here and
 * not only on the form, because this endpoint can be posted to directly, and
 * everything below writes to storage before the file reaches the database.
 */
if (!current_user_can_upload_files()) {
    dieWithError(__('You do not have permission to upload files.', 'cftp_admin'), 403);
}

/**
 * This endpoint renders no html, so it never passed through the check in
 * header.php and stayed reachable while a required enrollment was still
 * outstanding. It has to be refused here for the same reason the upload
 * permission is: the form is not the only way to reach it.
 */
if (totp_setup_is_required()) {
    dieWithError(__('Two-factor authentication is required for your account. Please set up an authenticator app before continuing.', 'cftp_admin'), 403);
}

/**
 * upload.php
 *
 * Copyright 2009, Moxiecode Systems AB
 * Released under GPL License.
 *
 * License: http://www.plupload.com/license
 * Contributing: http://www.plupload.com/contributing
 */
// HTTP headers for no cache etc
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Settings
$targetDir = UPLOADED_FILES_DIR;

$cleanupTargetDir = true; // Remove old files
$maxFileAge = 5 * 3600; // Temp file age in seconds

@set_time_limit(UPLOAD_TIME_LIMIT);

// Uncomment this one to fake upload time
// usleep(5000);

// Get parameters
$chunk = isset($_POST["chunk"]) ? intval($_POST["chunk"]) : 0;
$chunks = isset($_POST["chunks"]) ? intval($_POST["chunks"]) : 0;
$fileName = isset($_POST["name"]) ? $_POST["name"] : '';

// Validate file has an acceptable extension
if (!file_is_allowed($fileName)) {
    dieWithError('Invalid Extension');
}

// Create target dir
if (!file_exists($targetDir))
	@mkdir($targetDir);

// Check for directory traversal
$basePath = $targetDir . DS;
$realBase = realpath($basePath);

$filePath = dirname($basePath . $fileName);
$realFilePath = realpath($filePath);

if ($realFilePath === false || strpos($realFilePath, $realBase) !== 0) {
    dieWithError("Directory Traversal Detected!");
}

$filePath = $targetDir . DS . $fileName;

// Remove old temp files	
if ($cleanupTargetDir && is_dir($targetDir) && ($dir = @opendir($targetDir))) {
	while (($file = readdir($dir)) !== false) {
		$tmpfilePath = $targetDir . DS . $file;

		// Remove temp file if it is older than the max age and is not the current file
		if (preg_match('/\.part$/', $file) && (filemtime($tmpfilePath) < time() - $maxFileAge) && ($tmpfilePath != "{$filePath}.part")) {
			@unlink($tmpfilePath);
		}
	}

	closedir($dir);
} else
    dieWithError('Failed to open temp directory');
	

// Look for the content type header
if (isset($_SERVER["HTTP_CONTENT_TYPE"]))
	$contentType = $_SERVER["HTTP_CONTENT_TYPE"];

if (isset($_SERVER["CONTENT_TYPE"]))
	$contentType = $_SERVER["CONTENT_TYPE"];

// Handle non multipart uploads older WebKit versions didn't support multipart in HTML5
if (strpos($contentType, "multipart") !== false) {
	if (isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
		// Open temp file
		$out = fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");
		if ($out) {
			// Read binary input stream and append it to temp file
			$in = fopen($_FILES['file']['tmp_name'], "rb");

			if ($in) {
				while ($buff = fread($in, 4096))
					fwrite($out, $buff);
            } else
                dieWithError('Failed to open input stream');
			fclose($in);
			fclose($out);
			@unlink($_FILES['file']['tmp_name']);
        } else {
            dieWithError('Failed to open output stream');
        }
    } else {
        dieWithError('Failed to move uploaded file');
    }
} else {
	// Open temp file
	$out = fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");
	if ($out) {
		// Read binary input stream and append it to temp file
		$in = fopen("php://input", "rb");

		if ($in) {
			while ($buff = fread($in, 4096))
				fwrite($out, $buff);
        } else {
            dieWithError('Failed to open input stream');
        }

		fclose($in);
		fclose($out);
    } else {
        dieWithError('Failed to open output stream');
    }
}

// Check if file has been uploaded
if (!$chunks || $chunk == $chunks - 1) {
	// Strip the temp .part suffix off
	rename("{$filePath}.part", $filePath);

    // Get storage selection from request or use default
    $storage_selection = isset($_POST['storage_selection']) ? $_POST['storage_selection'] : get_option('default_upload_storage', 'local');

    /**
     * Encrypt when it is required, or when the uploader asked for it. Having
     * the feature enabled is not on its own a reason to encrypt: doing that
     * ignored the per upload checkbox and made the choice meaningless.
     */
    $encryption_required = \ProjectSend\Classes\Encryption::isRequired();
    $encryption_requested = isset($_POST['encrypt_file']) && $_POST['encrypt_file'] === '1';
    $encrypt_file = \ProjectSend\Classes\Encryption::isEnabled() && ($encryption_required || $encryption_requested);

    /**
     * The size limit is off by default, so this only applies to an
     * administrator who has deliberately set one. Over the limit the file
     * cannot be encrypted, so it is refused when encryption is required and
     * stored as is when it was merely requested.
     */
    if ($encrypt_file) {
        $max_size_mb = (int)get_option('files_encryption_max_file_size', null, '0');
        $file_size = filesize($filePath);

        if ($max_size_mb > 0 && $file_size !== false && $file_size > $max_size_mb * 1048576) {
            if ($encryption_required) {
                unlink($filePath);
                dieWithError(sprintf(__('This file is larger than the %s MB limit set for encryption, and encryption is mandatory.', 'cftp_admin'), $max_size_mb), 413);
            }

            $encrypt_file = false;
        }
    }

    // Encrypt file if requested/required
    if ($encrypt_file) {
        try {
            $encryption = new \ProjectSend\Classes\Encryption();

            // Generate unique file key
            $file_key = $encryption->generateFileKey();

            // Encrypt the file
            $encrypted_path = $filePath . '.encrypted';
            $encrypt_result = $encryption->encryptFile($filePath, $encrypted_path, $file_key);

            if (!$encrypt_result['success']) {
                /**
                 * The assembled upload is still plaintext on disk and is not
                 * referenced anywhere, so leaving it behind would be an orphan
                 * copy of a file the installation wanted encrypted.
                 */
                if (file_exists($encrypted_path)) {
                    unlink($encrypted_path);
                }
                unlink($filePath);

                error_log('ProjectSend: encryption failed for an upload: ' . $encrypt_result['error']);
                dieWithError(__('The file could not be encrypted and was not saved.', 'cftp_admin') . ' ' . $encrypt_result['error'], 500);
            }

            // Encrypt the file key with master key
            $encrypted_key_data = $encryption->encryptFileKey($file_key);

            // Replace original file with encrypted version
            unlink($filePath);
            rename($encrypted_path, $filePath);

            // Store encryption metadata for later use
            $encryption_metadata = [
                'encrypted' => 1,
                'encryption_key_encrypted' => $encrypted_key_data['encrypted_key'],
                'encryption_iv' => $encrypted_key_data['iv'],
                'encryption_algorithm' => $encryption->getAlgorithm(),
                'encryption_file_iv' => $encrypt_result['iv']
            ];

        } catch (\Exception $e) {
            error_log('File encryption error: ' . $e->getMessage());
            dieWithError('File encryption failed');
        }
    } else {
        $encryption_metadata = [
            'encrypted' => 0,
            'encryption_key_encrypted' => null,
            'encryption_iv' => null,
            'encryption_algorithm' => null,
            'encryption_file_iv' => null
        ];
    }

    // Add to database
    $file = new \ProjectSend\Classes\Files;

    // Set encryption metadata
    $file->encrypted = $encryption_metadata['encrypted'];
    $file->encryption_key_encrypted = $encryption_metadata['encryption_key_encrypted'];
    $file->encryption_iv = $encryption_metadata['encryption_iv'];
    $file->encryption_algorithm = $encryption_metadata['encryption_algorithm'];
    $file->encryption_file_iv = $encryption_metadata['encryption_file_iv'];

    // Route to appropriate storage based on selection
    $route_result = $file->routeToStorage($filePath, $storage_selection, $fileName);

    if ($route_result && isset($route_result['filename_original'])) {
        // setDefaults() must be called after routeToStorage() because it sets
        // title from filename_original, which is populated by generateSafeFilename()
        $file->setDefaults();
        $result = $file->addToDatabase();
    } else {
        $result = [
            'status' => 'error',
            'message' => __('Failed to process file upload to selected storage.', 'cftp_admin')
        ];
    }

    if ($result['status'] === 'success') {
        // Return JSON-RPC response
        $response = [
            'OK' => 1,
            'info' => [
                'id' => $file->getId(),
                'NewFileName' => $fileName,
                'encrypted' => $encryption_metadata['encrypted']
            ]
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
        http_response_code(200);
    } else {
        // Return error response in same format as dieWithError()
        $response = [
            'OK' => 0,
            'error' => [
                'code' => 400,
                'message' => $result['message'],
                'filename' => $fileName
            ]
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
        http_response_code(400);
    }
    exit;
}
