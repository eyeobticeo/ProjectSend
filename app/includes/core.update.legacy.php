<?php
/**
 * Update routine used on versions prior to 1.0
 *
 * @package		ProjectSend
 * @subpackage	Updates
 */

global $dbh;

/**
 * r92 updates
 * The logo file name is now stored on the database.
 * If the row doesn't exist, create it and add the default value.
 */
if (92 > LAST_UPDATE) {
    $new_database_values = array(
                                    'logo_filename' => 'logo.png'
                                );

    foreach ($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}

/**
 * r135 updates
 * The e-mail address used for notifications to new users, clients and files
 * can now be defined on the options page. When installing or updating, it
 * will default to the primary admin user's e-mail.
 */
if (135 > LAST_UPDATE) {
    $statement = $dbh->query("SELECT * FROM " . TABLE_USERS . " WHERE id = '1'");

    $statement->setFetchMode(PDO::FETCH_ASSOC);
    while ( $row = $statement->fetch() ) {
        $set_admin_email = $row['email'];
    }

    $new_database_values = array(
                                    'admin_email_address' => $set_admin_email
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}

/**
 * r183 updates
 * A new column was added on the clients table, to store the value of the
 * account active status.
 * If the column doesn't exist, create it. Also, mark every existing
 * client as active (1).
 */
if (183 > LAST_UPDATE) {
    /**
     * Add the "users can register" value to the options table.
     * Defaults to 0, since this is a new feature.
     * */
    $new_database_values = array(
                                    'clients_can_register' => '0'
                                );
    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}

/**
 * r189 updates
 * Move every uploaded file to a neutral location
 */
if (189 > LAST_UPDATE) {
    $work_folder = ROOT_DIR.'/upload/';
    $folders = glob($work_folder."*", GLOB_NOSORT);

    foreach ($folders as $folder) {
        if(is_dir($folder) && !stristr($folder,'/temp') && !stristr($folder,'/files')) {
            $files = glob($folder.'/*', GLOB_NOSORT);
            foreach ($files as $file) {
                if(is_file($file) && !stristr($file,'index.php')) {
                    $filename = basename($file);
                    $mark_for_moving[$filename] = $file;
                }
            }
        }
    }
    $work_folder = UPLOADED_FILES_DIR;
    if (!empty($mark_for_moving)) {
        foreach ($mark_for_moving as $filename => $path) {
            $new = UPLOADED_FILES_DIR.DS.$filename;
            $try_moving = rename($path, $new);
            chmod($new, 0644);
        }
    }
}

/**
 * r202 updates
 * Combine clients and users on the same table.
 */
if (202 > LAST_UPDATE) {
    try {
        $statement = $dbh->query("SELECT created_by FROM " . TABLE_USERS);
    } catch( PDOException $e ) {
        /* Mark existing users as active */
        $dbh->query("ALTER TABLE " . TABLE_USERS . " ADD address TEXT NULL, ADD phone varchar(32) NULL, ADD notify TINYINT(1) NOT NULL default='0', ADD contact TEXT NULL, ADD created_by varchar(32) NULL, ADD active TINYINT(1) NOT NULL default='1'");
        $dbh->query("INSERT INTO " . TABLE_USERS
                                ." (user, password, name, email, timestamp, address, phone, notify, contact, created_by, active, level)"
                                ." SELECT client_user, password, name, email, timestamp, address, phone, notify, contact, created_by, active, '0' FROM tbl_clients");
        $dbh->query("UPDATE " . TABLE_USERS . " SET active = 1");
        $updates_made++;
    }
}

/**
 * r210 updates
 * A new database table was added, that allows the creation of clients groups.
 */
if (210 > LAST_UPDATE) {
    if ( !table_exists( TABLE_GROUPS ) ) {
        /** Create the GROUPS table */
        $query = "
        CREATE TABLE IF NOT EXISTS `".TABLE_GROUPS."` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            `created_by` varchar(32) NOT NULL,
            `name` varchar(32) NOT NULL,
            `description` text NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ";
        $statement = $dbh->prepare($query);
        $statement->execute();

        $updates_made++;

        /**
         * r215 updates
         * Change the engine of every table to InnoDB, to use foreign keys on the
         * groups feature.
         * Included inside the previous update since that is not an officially
         * released version.
         */
            $original_basic_tables = array(
                                        TABLE_FILES,
                                        TABLE_OPTIONS,
                                        TABLE_USERS
                                    );
        foreach ($original_basic_tables as $working_table) {
            $statement = $dbh->prepare("ALTER TABLE $working_table ENGINE = InnoDB");
            $statement->execute();

            $updates_made++;
        }
    }
}

/**
 * r219 updates
 * A new database table was added.
 * Folders are related to clients or groups.
 */
if (219 > LAST_UPDATE) {
    if ( !table_exists( TABLE_FOLDERS ) ) {
        $query = "
        CREATE TABLE IF NOT EXISTS `".TABLE_FOLDERS."` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `parent` int(11) DEFAULT NULL,
            `name` varchar(32) NOT NULL,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            `client_id` int(11) DEFAULT NULL,
            `group_id` int(11) DEFAULT NULL,
            FOREIGN KEY (`parent`) REFERENCES ".TABLE_FOLDERS."(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            FOREIGN KEY (`client_id`) REFERENCES ".TABLE_USERS."(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            FOREIGN KEY (`group_id`) REFERENCES ".TABLE_GROUPS."(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ";
        $statement = $dbh->prepare($query);
        $statement->execute();

        $updates_made++;
    }
}

/**
 * r217 updates (after previous so the folder column can be created)
 * A new database table was added, to facilitate the relation of files
 * with clients and groups.
 */
if (217 > LAST_UPDATE) {
    if ( !table_exists( TABLE_FILES_RELATIONS ) ) {
        $query = "
        CREATE TABLE IF NOT EXISTS `".TABLE_FILES_RELATIONS."` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            `file_id` int(11) NOT NULL,
            `client_id` int(11) DEFAULT NULL,
            `group_id` int(11) DEFAULT NULL,
            `folder_id` int(11) DEFAULT NULL,
            `hidden` int(1) NOT NULL,
            `download_count` int(16) NOT NULL default '0',
            FOREIGN KEY (`file_id`) REFERENCES ".TABLE_FILES."(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            FOREIGN KEY (`client_id`) REFERENCES ".TABLE_USERS."(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            FOREIGN KEY (`group_id`) REFERENCES ".TABLE_GROUPS."(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            FOREIGN KEY (`folder_id`) REFERENCES ".TABLE_FOLDERS."(`id`) ON UPDATE CASCADE,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ";
        $statement = $dbh->prepare($query);
        $statement->execute();

        $updates_made++;
    }
}

/**
 * r241 updates
 * A new database table was added, that stores users and clients actions.
 */
if (241 > LAST_UPDATE) {
    if ( !table_exists( TABLE_LOG ) ) {
        $query = "
        CREATE TABLE IF NOT EXISTS `".TABLE_LOG."` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            `action` int(2) NOT NULL,
            `owner_id` int(11) NOT NULL,
            `owner_user` text DEFAULT NULL,
            `affected_file` int(11) DEFAULT NULL,
            `affected_account` int(11) DEFAULT NULL,
            `affected_file_name` text DEFAULT NULL,
            `affected_account_name` text DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ";
        $statement = $dbh->prepare($query);
        $statement->execute();

        $updates_made++;
    }
}

/**
 * r266 updates
 * Set timestamp columns as real timestamp data, instead of INT
 */
if (266 > LAST_UPDATE) {
    $statement = $dbh->query("ALTER TABLE `" . TABLE_USERS . "` ADD COLUMN `timestamp2` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()");
    $statement = $dbh->query("UPDATE `" . TABLE_USERS . "` SET `timestamp2` = FROM_UNIXTIME(`timestamp`)");
    $statement = $dbh->query("ALTER TABLE `" . TABLE_USERS . "` DROP COLUMN `timestamp`");
    $statement = $dbh->query("ALTER TABLE `" . TABLE_USERS . "` CHANGE `timestamp2` `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()");

    $updates_made++;
}

/**
 * r275 updates
 * A new database table was added.
 * It stores the new files-to clients relations to be
 * used on notifications.
 */
if (275 > LAST_UPDATE) {
    if ( !table_exists( TABLE_NOTIFICATIONS ) ) {
        $query = "
        CREATE TABLE IF NOT EXISTS `".TABLE_NOTIFICATIONS."` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            `file_id` int(11) NOT NULL,
            `client_id` int(11) NOT NULL,
            `upload_type` int(11) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ";
        $statement = $dbh->prepare($query);
        $statement->execute();

        $updates_made++;
    }
}

/**
 * r278 updates
 * Set timestamp columns as real timestamp data, instead of INT
 */
if (278 > LAST_UPDATE) {
    $statement = $dbh->query("ALTER TABLE `" . TABLE_FILES . "` ADD COLUMN `timestamp2` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()");
    $statement = $dbh->query("UPDATE `" . TABLE_FILES . "` SET `timestamp2` = FROM_UNIXTIME(`timestamp`)");
    $statement = $dbh->query("ALTER TABLE `" . TABLE_FILES . "` DROP COLUMN `timestamp`");
    $statement = $dbh->query("ALTER TABLE `" . TABLE_FILES . "` CHANGE `timestamp2` `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()");

    $updates_made++;
}


/**
 * r282 updates
 * Add new options to select the handler for sending emails.
 */
if (282 > LAST_UPDATE) {
    $new_database_values = array(
                                    'mail_system_use' => 'mail',
                                    'mail_smtp_host' => '',
                                    'mail_smtp_port' => '',
                                    'mail_smtp_user' => '',
                                    'mail_smtp_pass' => '',
                                    'mail_from_name' => THIS_INSTALL_SET_TITLE
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}

/**
 * r338 updates
 * The Members table wasn't being created on existing installations.
 */
if (338 > LAST_UPDATE) {
    if ( !table_exists( TABLE_MEMBERS ) ) {
        /** Create the MEMBERS table */
        $query = "
        CREATE TABLE IF NOT EXISTS `".TABLE_MEMBERS."` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            `added_by` varchar(32) NOT NULL,
            `client_id` int(11) NOT NULL,
            `group_id` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`client_id`) REFERENCES ".TABLE_USERS."(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            FOREIGN KEY (`group_id`) REFERENCES ".TABLE_GROUPS."(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ";
        $statement = $dbh->prepare($query);
        $statement->execute();

        $updates_made++;
    }
}

/**
 * r346 updates
 * chmod the cache folder and main files of timthumb to 775
 * @deprecated
 */
if (346 > LAST_UPDATE) {
    //update_chmod_timthumb();
}

/**
 * r348 updates
 * chmod the emails folder and files to 777
 */
if (348 > LAST_UPDATE) {
    update_chmod_emails();
}

/**
 * r352 updates
 * chmod the main system files to 644
 */
if (352 > LAST_UPDATE) {
    chmod_main_files();
}

/**
 * r353 updates
 * Create a new option to let the user decide wheter to
 * use the relative or absolute file url when generating
 * thumbnails with timthumb.php
 *
 * @deprecated
 */
if (353 > LAST_UPDATE) {
    $new_database_values = array(
                                    //'thumbnails_use_absolute' => '0'
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}

/**
 * r354 updates
 * Import the files relations (up until r335 it was
 * only one-to-one with clients) into the new database
 * table. This should have been done before r335 release.
 * Sorry :(
 */
if (354 > LAST_UPDATE) {
    import_files_relations();
}


/**
 * r358 updates
 * New columns where added to the notifications table, to
 * store values about the state of it.
 * If the columns don't exist, create them.
 */
if (358 > LAST_UPDATE) {
    try {
        $statement = $dbh->query("SELECT sent_status FROM " . TABLE_NOTIFICATIONS);
    } catch( PDOException $e ) {
        $statement = $dbh->query("ALTER TABLE " . TABLE_NOTIFICATIONS . " ADD sent_status INT(2) NOT NULL");
        $statement = $dbh->query("ALTER TABLE " . TABLE_NOTIFICATIONS . " ADD times_failed INT(11) NOT NULL");
        $updates_made++;
    }
}


/**
 * r364 updates
 * Add new options to send copies of notifications emails
 * to the specified addresses.
 */
if (364 > LAST_UPDATE) {
    $new_database_values = array(
                                    'mail_copy_user_upload' => '',
                                    'mail_copy_client_upload' => '',
                                    'mail_copy_main_user' => '',
                                    'mail_copy_addresses' => ''
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}


/**
 * r377 updates
 * Add new options to store the last time the system checked
 * for a new version.
 */
$today = date('d-m-Y');
if (377 > LAST_UPDATE) {
    $new_database_values = array(
                                    'version_last_check' => $today,
                                    'version_new_found' => '0',
                                    'version_new_number' => '',
                                    'version_new_url' => '',
                                    'version_new_chlog' => '',
                                    'version_new_security' => '',
                                    'version_new_features' => '',
                                    'version_new_important' => ''
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}


/**
 * r386 / r412 updates
 * Add new options to handle actions related to clients
 * self registrations.
 */
if (412 > LAST_UPDATE) {
    $new_database_values = array(
                                    'clients_auto_approve' => '0',
                                    'clients_auto_group' => '0',
                                    'clients_can_upload' => '1'
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}


/**
 * r419 updates
 * Add new options to customize the emails sent by the system.
 */
if (419 > LAST_UPDATE) {
    $new_database_values = array(
                                /**
                                 * On or Off fields
                                 * Each one corresponding to a type of email
                                 */
                                    'email_new_file_by_user_customize' => '0',
                                    'email_new_file_by_client_customize' => '0',
                                    'email_new_client_by_user_customize' => '0',
                                    'email_new_client_by_self_customize' => '0',
                                    'email_new_user_customize' => '0',
                                /**
                                 * Text fields
                                 * Each one corresponding to a type of email
                                 */
                                    'email_new_file_by_user_text' => '',
                                    'email_new_file_by_client_text' => '',
                                    'email_new_client_by_user_text' => '',
                                    'email_new_client_by_self_text' => '',
                                    'email_new_user_text' => ''
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}

/**
 * r426 updates
 * Add new options to customize the header and footer of emails.
 */
if (426 > LAST_UPDATE) {
    $new_database_values = array(
                                'email_header_footer_customize' => '0',
                                'email_header_text' => '',
                                'email_footer_text' => '',
                            );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}

/**
 * r442 updates
 * Add new options to customize the header and footer of emails.
 */
if (442 > LAST_UPDATE) {
    $new_database_values = array(
                                'email_pass_reset_customize' => '0',
                                'email_pass_reset_text' => '',
                            );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}

/**
 * r464 updates
 * New columns where added to the files table, to
 * set expiry dates and download limit.
 * Also, set a new option to hide or show expired
 * files to clients.
 */
if (464 > LAST_UPDATE) {
    try {
        $statement = $dbh->query("SELECT expires FROM " . TABLE_FILES);
    } catch( PDOException $e ) {
        $statement = $dbh->query("ALTER TABLE " . TABLE_FILES . " ADD expires INT(1) NOT NULL default '0'");
        $statement = $dbh->query("ALTER TABLE " . TABLE_FILES . " ADD expiry_date TIMESTAMP NOT NULL");
        $updates_made++;
    }

    $new_database_values = array(
                                'expired_files_hide' => '1',
                            );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}


/**
 * r474 updates
 * A new database table was added.
 * Each download will now be saved here, to distinguish
 * individual downloads even if the origin is a group.
 */
if (474 > LAST_UPDATE) {
    if ( !table_exists( TABLE_DOWNLOADS ) ) {
        $query = "
        CREATE TABLE IF NOT EXISTS `" . TABLE_DOWNLOADS . "` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `file_id` int(11) NOT NULL,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            FOREIGN KEY (`user_id`) REFERENCES " . TABLE_USERS . "(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            FOREIGN KEY (`file_id`) REFERENCES " . TABLE_FILES . "(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ";
        $statement = $dbh->prepare($query);
        $statement->execute();

        $updates_made++;
    }
}


/**
 * r475 updates
 * New columns where added to the files table, to
 * allow public downloads via a token.
 */
if (475 > LAST_UPDATE) {
    try {
        $statement = $dbh->query("SELECT public_allow FROM " . TABLE_FILES);
    } catch( PDOException $e ) {
        $sql1 = $dbh->query("ALTER TABLE " . TABLE_FILES . " ADD public_allow INT(1) NOT NULL default '0'");
        $sql2 = $dbh->query("ALTER TABLE " . TABLE_FILES . " ADD public_token varchar(32) NULL");
        $updates_made++;
    }
}


/**
 * r487 updates
 * Add new options to limit the retries of notifications emails
 * and also set an expiry date.
 */
if (487 > LAST_UPDATE) {
    $new_database_values = array(
                                    'notifications_max_tries' => '2',
                                    'notifications_max_days' => '15',
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}


/**
 * r490 updates
 * Set foreign keys to update the notifications table automatically.
 * Rows that references deleted users or files will be deleted
 * before adding the keys.
 */
if (490 > LAST_UPDATE) {
    $statement = $dbh->query("DELETE FROM " . TABLE_NOTIFICATIONS . " WHERE file_id NOT IN (SELECT id FROM " . TABLE_FILES . ")");
    $statement = $dbh->query("DELETE FROM " . TABLE_NOTIFICATIONS . " WHERE client_id NOT IN (SELECT id FROM " . TABLE_USERS . ")");
    $statement = $dbh->query("ALTER TABLE " . TABLE_NOTIFICATIONS . " ADD FOREIGN KEY (`file_id`) REFERENCES " . TABLE_FILES . "(`id`) ON DELETE CASCADE ON UPDATE CASCADE");
    $statement = $dbh->query("ALTER TABLE " . TABLE_NOTIFICATIONS . " ADD FOREIGN KEY (`client_id`) REFERENCES " . TABLE_USERS . "(`id`) ON DELETE CASCADE ON UPDATE CASCADE");
    $updates_made++;
}


/**
 * r501 updates
 * Migrate the download count on each client to the new table.
 */
if (501 > LAST_UPDATE) {
    $statement = $dbh->query("SELECT * FROM " . TABLE_FILES_RELATIONS . " WHERE client_id IS NOT NULL AND download_count > 0");
    if( $statement->rowCount() > 0 ) {
        $downloads = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ( $downloads as $key => $row ) {
            $download_count	= $row['download_count'];
            $client_id		= $row['client_id'];
            $file_id		= $row['file_id'];

            for ($i = 0; $i < $download_count; $i++) {
                $statement = $dbh->prepare("INSERT INTO " . TABLE_DOWNLOADS . " (file_id, user_id) VALUES (:file_id, :client_id)");
                $statement->bindParam(':file_id', $file_id, PDO::PARAM_INT);
                $statement->bindParam(':client_id', $client_id, PDO::PARAM_INT);
                $statement->execute();
            }
        }
        $updates_made++;
    }
}


/**
 * r528 updates
 * Add new options for email security, file types limits and
 * requirements for passwords.
 * and also set an expiry date.
 */
if (528 > LAST_UPDATE) {
    $new_database_values = array(
                                    'file_types_limit_to' => 'all',
                                    'pass_require_upper' => '0',
                                    'pass_require_lower' => '0',
                                    'pass_require_number' => '0',
                                    'pass_require_special' => '0',
                                    'mail_smtp_auth' => 'none'
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}



/**
 * r557 updates
 * Change the database collations
 */
if (557 > LAST_UPDATE) {
    $alter = array();
    $statement = $dbh->exec('ALTER DATABASE ' . DB_NAME . ' CHARACTER SET utf8 COLLATE utf8_general_ci');
    $statement = $dbh->query('SET foreign_key_checks = 0');
    $statement = $dbh->query('SHOW TABLES');
    $tables = $statement->fetchAll(PDO::FETCH_COLUMN);
    foreach ( $tables as $key => $table ) {
        $alter[$key] = $table;
    }
    foreach ( $alter as $key => $value ) {
        $statement = $dbh->prepare("ALTER TABLE $value DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci");
        $statement->execute();
    }
    $statement = $dbh->query('SET foreign_key_checks = 1');

    $updates_made++;
}



/**
 * r572 updates
 * No DB changes
 */
if (572 > LAST_UPDATE) {
    $updates_made++;
}

/**
 * r582 updates
 * No DB changes
 */
if (582 > LAST_UPDATE) {
    $updates_made++;
}


/**
 * r645 updates
 * Added an option to use the browser language instead of
 * the one on the config file.
 */
if (645 > LAST_UPDATE) {
    $new_database_values = array(
                                    'use_browser_lang' => '0',
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}

/**
 * r672 updates
 * Added an option to allow clients to delete their own uploads
 */
if (672 > LAST_UPDATE) {
    $new_database_values = array(
                                    'clients_can_delete_own_files' => '0',
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}

/**
 * r674 updates
 * Add the Google Sign in options to the database
 */
if (674 > LAST_UPDATE) {
    $new_database_values = array(
                                    'google_client_id' => '',
                                    'google_client_secret' => '',
                                    'google_signin_enabled' => '0',
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}


/**
 * r678 updates
 * A new database table was added.
 * Files categories.
 */
if (678 > LAST_UPDATE) {
    if ( !table_exists( TABLE_CATEGORIES ) ) {
        $query = "
        CREATE TABLE IF NOT EXISTS `".TABLE_CATEGORIES."` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(32) NOT NULL,
            `parent` int(11) DEFAULT NULL,
            `description` text NULL,
            `created_by` varchar(".MAX_USER_CHARS.") NULL,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            FOREIGN KEY (`parent`) REFERENCES ".TABLE_CATEGORIES."(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ";
        $statement = $dbh->prepare($query);
        $statement->execute();

        $updates_made++;
    }
}

/**
 * r680 updates
 * A new database table was added.
 * Relates files categories to files.
 */
if (680 > LAST_UPDATE) {
    if ( !table_exists( TABLE_CATEGORIES_RELATIONS ) ) {
        $query = "
        CREATE TABLE IF NOT EXISTS `".TABLE_CATEGORIES_RELATIONS."` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            `file_id` int(11) NOT NULL,
            `cat_id` int(11) NOT NULL,
            FOREIGN KEY (`file_id`) REFERENCES ".TABLE_FILES."(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            FOREIGN KEY (`cat_id`) REFERENCES ".TABLE_CATEGORIES."(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ";
        $statement = $dbh->prepare($query);
        $statement->execute();

        $updates_made++;
    }
}


/**
 * r737 updates
 * Add the reCAPTCHA options to the database
 */
if (737 > LAST_UPDATE) {
    $new_database_values = array(
                                    'recaptcha_enabled' => '0',
                                    'recaptcha_site_key' => '',
                                    'recaptcha_secret_key' => '',
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}


/**
 * r738 updates
 * New columns where added to the downloads table, to
 * store the ip and hostname of the user, and a boolean
 * fieled set to true for anonymous downloads (public files)
 */
if (738 > LAST_UPDATE) {
    try {
        $statement = $dbh->query("SELECT remote_ip FROM " . TABLE_DOWNLOADS);
    } catch( PDOException $e ) {
        $statement = $dbh->query("ALTER TABLE " . TABLE_DOWNLOADS . " ADD remote_ip varchar(45) NULL");
        $statement = $dbh->query("ALTER TABLE " . TABLE_DOWNLOADS . " ADD remote_host text NULL");
        $statement = $dbh->query("ALTER TABLE " . TABLE_DOWNLOADS . " ADD anonymous tinyint(1) NULL");
        $updates_made++;
    }
}

/**
 * r757 updates
 * Add new options that clients can set expiration date when Uploded New files
 */

if (757 > LAST_UPDATE) {
    $new_database_values = array(
                                    'clients_can_set_expiration_date' => '0'
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}

/**
 * r835 updates
 * Uploaded files now save the filename twice on the database. The original filename (to
 * use when downloading) and the filename on disk, so no 2 files with the same name exist.
 */

if (835 > LAST_UPDATE) {
    try {
        $statement = $dbh->query("SELECT original_url FROM " . TABLE_FILES);
    } catch( PDOException $e ) {
        $sql1 = $dbh->query("ALTER TABLE " . TABLE_FILES . " ADD original_url TEXT NULL AFTER `url`");
        $updates_made++;
    }
}


/**
 * r837 updates
 * Added an option to allow groups to be public so clients can manually opt-in and out of them.
 * Added an option to enable or disable the use of CKEDITOR in the files descriptions.
 */

if (837 > LAST_UPDATE) {
    try {
        $statement = $dbh->query("SELECT public FROM " . TABLE_GROUPS);
    } catch( PDOException $e ) {
        $sql1 = $dbh->query("ALTER TABLE " . TABLE_GROUPS . " ADD public tinyint(1) NOT NULL default '0'");
        $updates_made++;
    }

    $new_database_values = array(
                                    'clients_can_select_group' => 'none',
                                    'files_descriptions_use_ckeditor' => '0',
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}

/**
 * r840 updates
 * Add a new table to handle clients requests to groups
 */
if (840 > LAST_UPDATE) {
    if ( !table_exists( TABLE_MEMBERS_REQUESTS ) ) {
        /** Create the MEMBERS table */
        $query = "
        CREATE TABLE IF NOT EXISTS `".TABLE_MEMBERS_REQUESTS."` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            `requested_by` varchar(32) NOT NULL,
            `client_id` int(11) NOT NULL,
            `group_id` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`client_id`) REFERENCES ".TABLE_USERS."(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            FOREIGN KEY (`group_id`) REFERENCES ".TABLE_GROUPS."(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ";
        $statement = $dbh->prepare($query);
        $statement->execute();

        $updates_made++;
    }
}

/**
 * r841 updates
 * Added an option so every file can have it's landing page, even if it's not public
 */
if (841 > LAST_UPDATE) {
    $new_database_values = array(
                                    'enable_landing_for_all_files' => '0',
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}

/**
 * r842 updates
 * Added an option to set a different text on the footer
 */
if (842 > LAST_UPDATE) {
    $new_database_values = array(
                                    'footer_custom_enable' => '0',
                                    'footer_custom_content' => '',
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}

/**
 * r845 updates
 * Add new options to customize the emails subjects sent by the system.
 */
if (845 > LAST_UPDATE) {
    $new_database_values = array(
                                /**
                                 * On or Off fields
                                 * Each one corresponding to a type of email
                                 */
                                    'email_new_file_by_user_subject_customize' => '0',
                                    'email_new_file_by_client_subject_customize' => '0',
                                    'email_new_client_by_user_subject_customize' => '0',
                                    'email_new_client_by_self_subject_customize' => '0',
                                    'email_new_user_subject_customize' => '0',
                                    'email_pass_reset_subject_customize' => '0',
                                /**
                                 * Text fields
                                 * Each one corresponding to a type of email
                                 */
                                    'email_new_file_by_user_subject' => '',
                                    'email_new_file_by_client_subject' => '',
                                    'email_new_client_by_user_subject' => '',
                                    'email_new_client_by_self_subject' => '',
                                    'email_new_user_subject' => '',
                                    'email_pass_reset_subject' => '',
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}


/**
 * r859 updates
 * Added an option to prevent indexing by search engines
 */
if (859 > LAST_UPDATE) {
    $new_database_values = array(
                                    'privacy_noindex_site'	=> '0',
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}


/**
 * r882 updates
 * New columns where added to the users table, to
 * mark if a client self registered and the account
 * needs to be checked.
 */
if (882 > LAST_UPDATE) {
    try {
        $statement = $dbh->query("SELECT account_requested FROM " . TABLE_USERS);
    } catch( PDOException $e ) {
        $statement = $dbh->query("ALTER TABLE " . TABLE_USERS . " ADD account_requested INT(1) NOT NULL default '0'");
        $statement = $dbh->query("ALTER TABLE " . TABLE_USERS . " ADD account_denied INT(1) NOT NULL default '0'");
        $statement = $dbh->query("ALTER TABLE " . TABLE_MEMBERS_REQUESTS . " ADD denied INT(1) NOT NULL default '0'");
        $updates_made++;
    }
}


/**
 * r885 updates
 * Option to set max upload filesize per user
 */
if (885 > LAST_UPDATE) {
    $statement = $dbh->query("ALTER TABLE `" . TABLE_USERS . "` ADD COLUMN `max_file_size` int(20) NOT NULL DEFAULT '0'");
    $updates_made++;
}


/**
 * r950 updates
 * New emails for approved and denied accounts.
 */
if (950 > LAST_UPDATE) {
    $new_database_values = array(
                                /**
                                 * On or Off fields
                                 * Each one corresponding to a type of email
                                 */
                                    'email_account_approve_subject_customize' => '0',
                                    'email_account_deny_subject_customize' => '0',
                                    'email_account_approve_customize' => '0',
                                    'email_account_deny_customize' => '0',
                                /**
                                 * Text fields
                                 * Each one corresponding to a type of email
                                 */
                                    'email_account_approve_subject' => '',
                                    'email_account_deny_subject' => '',
                                    'email_account_approve_text' => '',
                                    'email_account_deny_text' => '',
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}


/**
 * r1003 updates
 * Add an email to the admin when a client changes requests to public groups
 */
if (1003 > LAST_UPDATE) {
    $new_database_values = array(
                                /**
                                 * On or Off field
                                 */
                                    'email_client_edited_subject_customize' => '0',
                                    'email_client_edited_customize' => '0',
                                /**
                                 * Text fields
                                 */
                                    'email_client_edited_subject' => '',
                                    'email_client_edited_text' => '',
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}


/**
 * r1004 updates
 * Add new options for the landing page of public groups and files
 */

if (1004 > LAST_UPDATE) {
    $new_database_values = array(
                                    'public_listing_page_enable' => '0',
                                    'public_listing_logged_only' => '0',
                                    'public_listing_show_all_files' => '0',
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}

/**
 * r1005 updates
 * Add new options for the landing page of public groups and files
 */

if (1005 > LAST_UPDATE) {
    $new_database_values = array(
                                    'public_listing_use_download_link' => '0',
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}

/**
 * r1006 updates
 * 1- New column, public_token for public groups links
 * 2- Set public token for each group
 */
if (1006 > LAST_UPDATE) {
    try {
        $statement = $dbh->query("SELECT public_token FROM " . TABLE_GROUPS);
    } catch( PDOException $e ) {
        $statement = $dbh->query("ALTER TABLE " . TABLE_GROUPS . " ADD public_token varchar(32) NULL");
        $updates_made++;
    }

    $statement = $dbh->prepare("SELECT id FROM " . TABLE_GROUPS);
    $statement->execute();
    $statement->setFetchMode(PDO::FETCH_ASSOC);
    while( $group = $statement->fetch() ) {
        $public_token = generateRandomString(32);
        $statement2 = $dbh->prepare("UPDATE " . TABLE_GROUPS . " SET public_token=:token WHERE id=:id");
        $statement2->bindParam(':token', $public_token);
        $statement2->bindParam(':id', $group['id'], PDO::PARAM_INT);
        $statement2->execute();
        $updates_made++;
    }
}

/**
 * r1083 updates
 * Add Terms and conditions and Privacy policy pages
 */
if (1083 > LAST_UPDATE) {
    $new_database_values = array(
                                    'page_policy_enable' => '0',
                                    'page_policy_title' => '',
                                    'page_policy_content' => '',
                                );

    foreach($new_database_values as $row => $value) {
        if ( add_option_if_not_exists($row, $value) ) {
            $updates_made++;
        }
    }
}

/**
 * r1088 updates
 * Started replacing timthumb with SimpleImage
 */
if (1089 > LAST_UPDATE) {
    @chmod(THUMBNAILS_FILES_DIR, 0755);
    $updates_made++;
}

/**
 * r1097 updates
 * Password field length changed fomr 60 to 255 as per php's
 * password_hash docs recommendations
 */
if (1097 > LAST_UPDATE) {
    $statement = $dbh->query("ALTER TABLE " . TABLE_USERS . " CHANGE `password` `password` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL");
    $updates_made++;
}