<?php 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function print_result_and_exit($success, $message) {
    header('Content-Type: application/json');
    echo json_encode(array('success' => $success, 'message' => $message));
    exit;
}

function redirect_and_exit($url) {
    header('Location: '.$url, true, 302);
    exit;
}

function handle_output_and_exit($redirect_mode, $redirect_url, $success, $message) {
    if ($redirect_mode) {
        redirect_and_exit($redirect_url);
    }
    else {
        print_result_and_exit($success, $message);
    }
}

function init_db($db_host, $db_user, $db_password, $db_name) {
    $db;
    try {
        $db = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_password);
        $db->exec('SET CHARACTER SET utf8');
        // Use real prepared statements instead of pdo emulated prepare statements
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    } catch (PDOException $e) {
        if ($GLOBALS['PRINT_ERRORS'] === true) {
            echo "\n ERROR in method".__METHOD__.": ".$e->getMessage()."\n";
        }
        
        return false;
    }
    return $db;
}

function table_exists($db, $table_name) {
    try {
        $result = $db->query("SELECT 1 FROM $table_name LIMIT 1");
    } catch (Exception $e) {
        return false;
    }
    return $result !== false;
}

function create_newsletter_table($db, $table_name) {    
    // Create newsletter table when table not exists
    $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
        `id` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
        `email` VARCHAR(96) NOT NULL,
        `language` varchar(3),
        `confirmation_code` VARCHAR(96),
        `unsubscribe_code` VARCHAR(96),
        `datetime_confirm` DATETIME DEFAULT NULL,
        `date_added` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `date_updated` DATETIME ON UPDATE CURRENT_TIMESTAMP
        ) CHARSET=utf8";
    $db->exec($sql);
}

function add_newsletter_entry($db, $table_name, $email, $language, $confirmation_code, $unsubscribe_code) {
    $sql = "INSERT INTO `$table_name` (
        `email`, `language`, `confirmation_code`, `unsubscribe_code`
    )
    VALUES (
        :email, :language, :confirmation_code, :unsubscribe_code
    )";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        if ($GLOBALS['PRINT_ERRORS'] === true) {
            echo "\n ERROR in method '".__METHOD__."':";
            echo "\n Prepare failed PDO::errorInfo():\n";
            print_r($db->errorInfo());
        }
        return false;
    }
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':language', $language);
    $stmt->bindParam(':confirmation_code', $confirmation_code);
    $stmt->bindParam(':unsubscribe_code', $unsubscribe_code);
    $result = $stmt->execute();
    if (!$result) {
        if ($GLOBALS['PRINT_ERRORS'] === true) {
            echo "\n ERROR in method:'".__METHOD__."':";
            echo "\n Execute failed PDO::errorInfo():\n";
            print_r($db->errorInfo());
        }
        return false;
    }
    return true;
}

function confirm_email_address($db, $table_name, $confirmation_code, $days_to_confirm) {
    $sql = "UPDATE `$table_name`
        SET 
        `datetime_confirm` = NOW(),
        `confirmation_code` = NULL
        WHERE 
        `confirmation_code` = :confirmation_code
        AND 
        `date_added` >= NOW() - INTERVAL $days_to_confirm DAY";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            if ($GLOBALS['PRINT_ERRORS'] === true) {
                echo "\n ERROR in method:'".__METHOD__."':";
                echo "\n Prepare failed PDO::errorInfo():\n";
                print_r($db->errorInfo());
            }
            return false;
        }
        $stmt->bindParam(':confirmation_code', $confirmation_code);
        $result = $stmt->execute();
        if (!$result) {
            if ($GLOBALS['PRINT_ERRORS'] === true) {
                echo "\n ERROR in method:'".__METHOD__."':";
                echo "\n Execute failed PDO::errorInfo():\n";
                print_r($db->errorInfo());
            }
            return false;
        }
        if ($stmt->rowCount() < 1) {
            if ($GLOBALS['PRINT_ERRORS'] === true) {
                echo "\n ERROR in method:'".__METHOD__."':";
                echo "\n Row count is < 1 after performing action\n";
            }
            return false;
        }
        return true;
}

function unsubscribe_by_email($db, $table_name, $email) {
    $sql = "DELETE FROM `$table_name`
        WHERE
        `email` = :email";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        if ($GLOBALS['PRINT_ERRORS'] === true) {
            echo "\n ERROR in method:'".__METHOD__."':";
            echo "\n Prepare failed PDO::errorInfo():\n";
            print_r($db->errorInfo());
        }
        return false;
    }
    $stmt->bindParam(':email', $email);
    $result = $stmt->execute();
    if (!$result) {
        if ($GLOBALS['PRINT_ERRORS'] === true) {
            echo "\n ERROR in method:'".__METHOD__."':";
            echo "\n Execute failed PDO::errorInfo():\n";
            print_r($db->errorInfo());
        }
        return false;
    }
    return true;
}

// function unsubscribe_by_uid($db, $table_name, $u_id) {
//     $sql = "DELETE FROM `$table_name`
//         WHERE
//         `unsubscribe_code` = :u_id";
//     $stmt = $db->prepare($sql);
//     if (!$stmt) {
//         if ($GLOBALS['PRINT_ERRORS'] === true) {
//             echo "\n ERROR in method:'".__METHOD__."':";
//             echo "\n Prepare failed PDO::errorInfo():\n";
//             print_r($db->errorInfo());
//         }
//         return false;
//     }
//     $stmt->bindParam(':u_id', $u_id);
//     $result = $stmt->execute();
//     if (!$result) {
//         if ($GLOBALS['PRINT_ERRORS'] === true) {
//             echo "\n ERROR in method:'".__METHOD__."':";
//             echo "\n Execute failed PDO::errorInfo():\n";
//             print_r($db->errorInfo());
//         }
//         return false;
//     }
//     return true;
// }

function get_uid_by_cid($db, $table_name, $c_id) {
    $sql = "SELECT `unsubscribe_code`
        FROM `$table_name`
        WHERE
        `confirmation_code` = :c_id";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        if ($GLOBALS['PRINT_ERRORS'] === true) {
            echo "\n ERROR in method:'".__METHOD__."':";
            echo "\n Prepare failed PDO::errorInfo():\n";
            print_r($db->errorInfo());
        }
        return false;
    }
    $stmt->bindParam(':c_id', $c_id);
    $result = $stmt->execute();
    if (!$result) {
        if ($GLOBALS['PRINT_ERRORS'] === true) {
            echo "\n ERROR in method:'".__METHOD__."':";
            echo "\n Execute failed PDO::errorInfo():\n";
            print_r($db->errorInfo());
        }
        return false;
    }
    if ($stmt->rowCount() < 1) {
        return false;
    }
    $row = $stmt->fetch();
    return $row['unsubscribe_code'];
}

function get_email_by_uid($db, $table_name, $u_id) {
    $sql = "SELECT `email`
        FROM `$table_name`
        WHERE
        `unsubscribe_code` = :u_id";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        if ($GLOBALS['PRINT_ERRORS'] === true) {
            echo "\n ERROR in method:'".__METHOD__."':";
            echo "\n Prepare failed PDO::errorInfo():\n";
            print_r($db->errorInfo());
        }
        return false;
    }
    $stmt->bindParam(':u_id', $u_id);
    $result = $stmt->execute();
    if (!$result) {
        if ($GLOBALS['PRINT_ERRORS'] === true) {
            echo "\n ERROR in method:'".__METHOD__."':";
            echo "\n Execute failed PDO::errorInfo():\n";
            print_r($db->errorInfo());
        }
        return false;
    }
    if ($stmt->rowCount() < 1) {
        return false;
    }
    $row = $stmt->fetch();
    return $row['email'];
}


function clean_newsletter_table($db, $table_name, $days_to_confirm) {
    if (!table_exists($db, $table_name)) {
        return false;
    }
    $sql = "DELETE FROM `$table_name`
        WHERE
        `date_added` < NOW() - INTERVAL $days_to_confirm DAY
        AND 
        `confirmation_code` IS NOT NULL";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        if ($GLOBALS['PRINT_ERRORS'] === true) {
            echo "\n ERROR in method:'".__METHOD__."':";
            echo "\n Prepare failed PDO::errorInfo():\n";
            print_r($db->errorInfo());
        }
        return false;
    }
    $result = $stmt->execute();
    if (!$result) {
        if ($GLOBALS['PRINT_ERRORS'] === true) {
            echo "\n ERROR in method:'".__METHOD__."':";
            echo "\n Execute failed PDO::errorInfo():\n";
            print_r($db->errorInfo());
        }
        return false;
    }
    return true;
}

function send_confirmation_request_email($email, $email_from, $reply_to, $confirmation_code, $redirect_mode) {
    global $ROOT_PATH;
    // Split url into base url and get params 
    $uri_parts = explode('?', $_SERVER['REQUEST_URI'], 2);
    $url = "https://".$_SERVER['HTTP_HOST'].$uri_parts[0];
    // Send confirmation email
    $subject = file_get_contents($ROOT_PATH."templates/subscribtion-request/subject.txt");
    $confirmation_url = $url . '?c_id=' . $confirmation_code;
    if ($redirect_mode) {
        $confirmation_url = $confirmation_url . '&redirect=true&redirect_resulting_email=true';
    }
    $message = file_get_contents($ROOT_PATH."templates/subscribtion-request/body.html");
    $message = str_replace("{confirmation_url}", $confirmation_url, $message);
    $headers = 'MIME-Version: 1.0' . "\r\n" .
        'Content-type: text/html; charset=utf-8' . "\r\n" .
        "From: $email_from" . "\r\n" .
        "Reply-To: $reply_to" . "\r\n" .
        'X-Mailer: PHP/' . phpversion();
        // Send email to customer
    mail($email, $subject, $message, $headers);
}

function send_successfully_subscribed_email($email, $email_from, $reply_to, $u_id, $redirect_mode) {
    global $ROOT_PATH;
    // Split url into base url and get params 
    $uri_parts = explode('?', $_SERVER['REQUEST_URI'], 2);
    $url = "https://".$_SERVER['HTTP_HOST'].$uri_parts[0];
    // Send confirmation email
    $subject = file_get_contents($ROOT_PATH."templates/successfully-subscribed/subject.txt");
    $unsubscribe_url = $url . '?u_id=' . $u_id;
    if ($redirect_mode) {
        $unsubscribe_url = $unsubscribe_url . '&redirect=true';
    }
    $message = file_get_contents($ROOT_PATH."templates/successfully-subscribed/body.html");
    $message = str_replace("{unsubscribe_url}", $unsubscribe_url, $message);
    $headers = 'MIME-Version: 1.0' . "\r\n" .
        'Content-type: text/html; charset=utf-8' . "\r\n" .
        "From: $email_from" . "\r\n" .
        "Reply-To: $reply_to" . "\r\n" .
        'X-Mailer: PHP/' . phpversion();
        // Send email to customer
    mail($email, $subject, $message, $headers);
}

$ROOT_PATH = __DIR__."/";

$redirect_mode = false;
if (isset($_GET['redirect']) && $_GET['redirect'] == 'true') {
    $redirect_mode = true;
}
// When set to true, urls in email will get 'redirect'flag to activate redirect_mode.
// In confirmation email for example
$redirect_resulting_email = false;
if (isset($_GET['redirect_resulting_email']) && $_GET['redirect_resulting_email'] == 'true') {
    $redirect_resulting_email = true;
}



$CONFIG = parse_ini_file($ROOT_PATH.'config/config.ini', true);
$NEWSLETTER_TABLE_NAME = $CONFIG['GENERAL']['newsletter_table_name'];
$EMAIL_FROM = $CONFIG['GENERAL']['email_from'];
$EMAIL_REPLY_TO = $CONFIG['GENERAL']['email_repy_to'];
$DAYS_TO_CONFIRM = $CONFIG['GENERAL']['time_to_confirm'];
$SEND_SUCCESSFULLY_SUBSCRIBED_MAIL = $CONFIG['GENERAL']['send_successfully_subscribed_email'] == "true" ? true : false;
$PRINT_ERRORS = ($CONFIG['GENERAL']['print_errors'] === "true") ? true : false;


$DB_HOST = $CONFIG['DATABASE']['host'];
$DB_USER = $CONFIG['DATABASE']['user'];
$DB_PASSWORD = $CONFIG['DATABASE']['password'];
$DB_NAME = $CONFIG['DATABASE']['name'];
$db = init_db($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_NAME);
if ($db === false) {
    handle_output_and_exit($redirect_mode, $CONFIG['REDIRECT']['url_technical_error'], false, "error_technical");
}

// Clean Newsletter table at every call
clean_newsletter_table($db, $NEWSLETTER_TABLE_NAME, $DAYS_TO_CONFIRM);


if (isset($_GET['subscribe'])) {
    $email = "";
    if (isset($_POST['email'])) {
        $email = $_POST['email'];
    }
    if (!is_null($email) && $email !== "" && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $language = 'de';
        $confirmation_code = md5("" . uniqid() . random_int(0, 9999999));
        $unsubscribe_code = md5("" . uniqid() . random_int(0, 9999999));
        
        // Add email data to db
        $result = add_newsletter_entry($db, $NEWSLETTER_TABLE_NAME, $email, $language, $confirmation_code, $unsubscribe_code);
        if ($result === true) {
            // Send confirmation email
            send_confirmation_request_email($email, $EMAIL_FROM, $EMAIL_REPLY_TO, $confirmation_code, $redirect_resulting_email);
            handle_output_and_exit($redirect_mode, $CONFIG['REDIRECT']['url_subscribe_success'], true, "");
        }
        else {
            handle_output_and_exit($redirect_mode, $CONFIG['REDIRECT']['url_subscribe_failed_saving_email_error'], false, "error_saving_email");
        }
    }
    else {
        handle_output_and_exit($redirect_mode, $CONFIG['REDIRECT']['url_subscribe_failed_invalid_email'], false, "error_invalid_email");
    }
}
// Confirm email address
else if (isset($_GET['c_id'])) {
    $c_id = $_GET['c_id'];
    if (!is_null($c_id) && $c_id !== "") {
        $u_id = get_uid_by_cid($db, $NEWSLETTER_TABLE_NAME, $c_id);
        $result = confirm_email_address($db, $NEWSLETTER_TABLE_NAME, $c_id, $DAYS_TO_CONFIRM);
        if ($result === true) {
            if ($SEND_SUCCESSFULLY_SUBSCRIBED_MAIL && $u_id !== false) {
                $email = get_email_by_uid($db, $NEWSLETTER_TABLE_NAME, $u_id);
                send_successfully_subscribed_email($email, $EMAIL_FROM, $EMAIL_REPLY_TO, $u_id, $redirect_resulting_email);
            }
            handle_output_and_exit($redirect_mode, $CONFIG['REDIRECT']['url_confirm_email_success'], true, "");
        }
        else {
            handle_output_and_exit($redirect_mode, $CONFIG['REDIRECT']['url_confirm_email_failed_general_error'], false, "error_confirming_email");
        }
    }
    else {
        handle_output_and_exit($redirect_mode, $CONFIG['REDIRECT']['url_confirm_email_failed_invalid_cid'], false, "invalid_c_id");
    }
}
// Unsubscribe by email
else if(isset($_GET['unsubscribe'])) {
    $email = "";
    if (isset($_POST['email'])) {
        $email = $_POST['email'];
    }
    
    if (!is_null($email) && $email !== "" && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result = unsubscribe_by_email($db, $NEWSLETTER_TABLE_NAME, $email);
        if ($result === true) {
            handle_output_and_exit($redirect_mode, $CONFIG['REDIRECT']['url_unsubscribe_email_success'], true, "");
        }
        else {
            handle_output_and_exit($redirect_mode, $CONFIG['REDIRECT']['url_unsubscribe_email_failed_general_error'], false, "error_unsubscribing");
        }
    }
    else {
        handle_output_and_exit($redirect_mode, $CONFIG['REDIRECT']['url_unsubscribe_email_failed_invalid_email'], false, "error_invalid_email");
    }
}
// Unsubscribe by unsubscribtion code
else if (isset($_GET['u_id'])) {
    $u_id = $_GET['u_id'];
    if (!is_null($u_id) && $u_id !== "") {
        // Unsubscribe by email, so when user is subscribed multiple times the user will 
        // get unsubscribed from all entries (all entries with the email will get deleted)
        $email = get_email_by_uid($db, $NEWSLETTER_TABLE_NAME, $u_id);
        $result = unsubscribe_by_email($db, $NEWSLETTER_TABLE_NAME, $email);
        if ($result === true) {
            handle_output_and_exit($redirect_mode, $CONFIG['REDIRECT']['url_unsubscribe_uid_success'], true, "");
        }
        else {
            handle_output_and_exit($redirect_mode, $CONFIG['REDIRECT']['url_unsubscribe_uid_failed_general_error'], false, "error_unsubscribing");
        }
    }
    else {
        handle_output_and_exit($redirect_mode, $CONFIG['REDIRECT']['url_unsubscribe_uid_failed_invalid_uid'], false, "error_invalid_u_id");
    }
}
else if(isset($_GET['installdb'])) {
    create_newsletter_table($db, $NEWSLETTER_TABLE_NAME);
    handle_output_and_exit($redirect_mode, $CONFIG['REDIRECT']['url_install_db_success'], true, "");
}
?>