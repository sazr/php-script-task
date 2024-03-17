<?php

class UserUpload {

    public const DB_NAME = "website_users";

    public function handle_directive() {
        $options           = getopt("u:p:h:", ["file:", "create_table", "dry_run", "help"]);
        $file              = ! empty($options['file']) ? $options['file'] : null;
        $create_table      = isset($options['create_table']);
        $help              = isset($options['help']);
        $db_host           = ! empty($options['h']) ? $options['h'] : null;
        $db_username       = ! empty($options['u']) ? $options['u'] : null;
        $db_password       = ! empty($options['p']) ? $options['p'] : null;
        $is_dry_run        = isset($options['dry_run']);
        $db_props_provided = ! empty( $db_host ) && ! empty( $db_username ) && ! empty( $db_password );

        if ( $create_table ) {
            if ( ! $db_props_provided ) {
                echo "MySQL username (-u), password (-p), and host (-h) are required when using --file or --create_table options.\n";
                return;
            }

            $this->handle_build_user_table( $db_host, $db_username, $db_password, true );
        } elseif ( $file ) {
            if ( ! $db_props_provided && ! $is_dry_run ) {
                echo "MySQL username (-u), password (-p), and host (-h) are required when using --file or --create_table options.\n";
                return;
            }

            $users = $this->parse_users_csv( $file );
            if ( empty( $users ) ) {
                return;
            }

            if ( $is_dry_run ) {
                return;
            }

            // Create the database/table just incase it doesn't exist yet
            $this->handle_build_user_table( $db_host, $db_username, $db_password, false );

            $this->handle_import_users( $users, $db_host, $db_username, $db_password );
        } elseif ( $help ) {
            $this->handle_help();
        } else {
            echo "Invalid directive\n";
            $this->handle_help();
        }
    }

    public function handle_build_user_table( $db_host, $db_username, $db_password, $cleanse = false ) {
        $mysqli = new mysqli($db_host, $db_username, $db_password);
        
        try {
            if ($mysqli->connect_error) {
                throw new Exception("Connection failed: " . $mysqli->connect_error);
            }

            $mysqli->begin_transaction();

            // Create database if it doesn't exist. I notice the task documentation didn't instruct adding a database name command line argument. So I have made an assumption I need to create the database as part of the task.
            $sql = "CREATE DATABASE IF NOT EXISTS website_users CHARACTER SET utf8 COLLATE utf8_general_ci;";
            $result = $mysqli->query($sql);

            if ( $result !== TRUE ) {
                throw new Exception("Error creating database: " . $mysqli->error);
            }

            $mysqli->select_db( "website_users" );
            if ($mysqli->error) {
                throw new Exception("Error using database: " . $mysqli->error);
            }

            if ( $cleanse ) {
                $sql = "DROP TABLE IF EXISTS users;";
                $result = $mysqli->query($sql);

                if ( $result !== TRUE ) {
                    throw new Exception("Error dropping table: " . $mysqli->error);
                }
            }

            $sql = "CREATE TABLE IF NOT EXISTS users (
                email VARCHAR(255) PRIMARY KEY UNIQUE,
                name VARCHAR(255) NOT NULL,
                surname VARCHAR(255) NOT NULL
            );";
            $result = $mysqli->query($sql);

            if ( $result !== TRUE ) {
                throw new Exception("Error creating table: " . $mysqli->error);
            }

            $mysqli->commit();
            echo "Table created successfully\n";
        }
        catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            $mysqli->rollback();
        } 
        finally {
            $mysqli->close();
        }
    }

    public function handle_import_users( $users, $db_host, $db_username, $db_password, $is_dry_run = false ) {
        $mysqli = new mysqli($db_host, $db_username, $db_password, "website_users");

        try {
            if ($mysqli->connect_error) {
                throw new Exception("Connection failed: " . $mysqli->connect_error);
            }

            if (!$mysqli->set_charset("utf8")) {
                throw new Exception("Error setting charset: " . $mysqli->error);
            }

            $mysqli->begin_transaction();

            foreach ($users as $user) {
                $sql = "INSERT INTO users (email, name, surname) VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE name = VALUES(name), surname = VALUES(surname)";
                $stmt = $mysqli->prepare($sql);
                
                if (!$stmt) {
                    throw new Exception("Error preparing statement: " . $mysqli->error);
                }

                // Handle special characters like German umlauts (ö), apostrophes for Irish names, etc. I've had this issue before, specifically ö, and learnt my lesson.
                $email = mb_convert_encoding($user['email'], "UTF-8");
                $name = mb_convert_encoding($user['name'], "UTF-8");
                $surname = mb_convert_encoding($user['surname'], "UTF-8");
                $stmt->bind_param("sss", $email, $name, $surname);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error inserting row: " . $stmt->error);
                }
            }

            if ($is_dry_run) {
                $mysqli->rollback();
            } else {
                $mysqli->commit();
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            $mysqli->rollback();
        } finally {
            $mysqli->close();
        }
    }

    public function handle_help() {
        echo <<<USAGE
Usage: php user_upload.php [options]
Options:
  --file [csv file name]           The name of the CSV to be parsed.
  --create_table                   This will cause the MySQL users table to be built (and no further action will be taken).
  --dry_run                        This will be used with the --file directive in case we want to run the script but not insert into the DB. All other functions will be executed, but the database won't be altered.
  -u                               MySQL username.
  -p                               MySQL password.
  -h                               MySQL host.
  --help                           Help documentation
USAGE;
    }

    public function parse_users_csv( $file ) {
        $users = array();

        if (!file_exists($file) || !is_file($file) || !is_readable($file)) {
            echo "File does not exist, is not a file, is not readable or file name is badly formatted: $file\n";
            return false;
        }

        $file_handle   = fopen($file, 'r, ,', 'UTF-8');
        if ($file_handle === false) {
            echo "Failed to open file: $file\n";
            return false;
        }

        $headers = fgetcsv($file_handle);
        if ( empty($headers) ) {
            echo "No header found in file: $file\n";
            return false;
        }

        // Sanitise header data.
        $headers = array_reduce($headers, function($result, $header) {
            $heading = filter_var($header, FILTER_SANITIZE_STRING);
            if (!empty($heading)) {
                $result[] = $this->format_string($heading);
            }
            return $result;
        }, []);
        
        while ( $row = fgetcsv($file_handle) ) {
            $user = array_combine($headers, $row);

            if ( empty($user['email']) || empty($user['name']) || empty($user['surname']) ) {
                echo "Invalid row: " . json_encode( $user ) . "\n";
            }

            // Sanitise/validate data.
            $user['email']   = filter_var($user['email'], FILTER_SANITIZE_EMAIL);
            $user['name']    = filter_var($user['name'], FILTER_UNSAFE_RAW | FILTER_SANITIZE_STRING);
            $user['surname'] = filter_var($user['surname'], FILTER_UNSAFE_RAW | FILTER_SANITIZE_STRING);

            if ( empty($user['email']) || empty($user['name']) || empty($user['surname']) ) {
                // Assumption: The task document says "In case that an email is invalid, no insert should be made to database and an error message should be reported to STDOUT". I'm assuming echo is ok for STDOUT here and you're not asking for error_log() or something else.
                echo "Invalid data format: " . json_encode( $user ) . "\n";
                return false;
            }
            // Validate email.
            if ( ! $this->validate_email( $user['email'] ) ) {
                echo "Invalid email: " . json_encode( $user ) . "\n";
                return false;
            }
            
            // Format data.
            $user['email']   = $this->format_email( $user['email'] );
            $user['name']    = $this->format_string( $user['name'], true );
            $user['surname'] = $this->format_string( $user['surname'], true );
            $users[]         = $user;
        }

        fclose($file_handle);

        if ( empty ($users ) ) {
            echo "No valid users found in file.";
            return false;
        }

        return $users;
    }

    public function format_string( $value, $capitalise = false ) {
        // Assumption: making an assumption that ! is ok in a name/surname. For eg Sam!!. If not, I can remove it.
        $pattern  = '/^\s+|[\x00-\x1F\x7F]|[\r\n\t"]|[^\p{L}\p{N}\p{P}\p{S}\p{Z}]|\s+$/u';
        $value    = strtolower( preg_replace( $pattern, '', $value ) );

        if ( $capitalise ) {
            $value = ucwords( $value );
        }

        return $value;
    }

    public function format_email( $value  ) {
        $pattern  = '/^\s+|[\x00-\x1F\x7F]|[\r\n\t"]|[^\p{L}\p{N}\p{P}\p{S}\p{Z}]|\s+$/u';
        $value    = strtolower( preg_replace( $pattern, '', $value ) );
        return $value;
    }

    /**
     * Validate email address. Credit: https://phppot.com/php/php-validate-email/
     * 
     * @param string $email
     * @return bool
     */
    public function validate_email($email) {
        $emailRegex = '/^(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){255,})(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){65,}@)(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E\\pL\\pN]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F\\pL\\pN]|(?:\\x5C[\\x00-\\x7F]))*\\x22))(?:\\.(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E\\pL\\pN]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F\\pL\\pN]|(?:\\x5C[\\x00-\\x7F]))*\\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-+[a-z0-9]+)*\\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-+[a-z0-9]+)*)|(?:\\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\\]))$/iDu';

        return preg_match( $emailRegex, strtolower( $email ) ) === 1;
    }
}

$user_upload = new UserUpload();
$user_upload->handle_directive();
?>