<?php

class UserUpload {
    public $commit_changes = true;

    public function __construct() {
        // TODO: initialise a logger (over-engineering?)
    }

    public function handle_directive() {
        // Parse command line args
        // Determine directive
        // Validate directive specific arguments are present
        // Call directive handler
    }

    public function handle_directive_help() {

    }

    public function handle_directive_build_user_table() {

    }

    public function handle_directive_import_users() {

    }

    public function handle_directive_dry_run_import_users() {

    }

    public function handle_directive_mysql_property() {

    }

    public function get_create_user_table( $rebuild = false ) {

    }
}

$user_upload = new UserUpload();
$user_upload->handle_directive();
?>