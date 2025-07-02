<?php
declare(strict_types=1);

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Initialize the database connection
 *
 * @param  string|array $params Database connection group or DSN string or config array
 * @param  bool|null    $active_record_override Whether to use active record
 * @return mixed        Database connection object
 * @throws Exception    If configuration or DSN is invalid
 */
function &DB($params = '', $active_record_override = null)
{
    // Load config if not DSN string
    if (is_string($params) && strpos($params, '://') === false) {
        $envPath = defined('ENVIRONMENT') ? APPPATH . 'config/' . ENVIRONMENT . '/database.php' : '';
        $defaultPath = APPPATH . 'config/database.php';

        if (!empty($envPath) && file_exists($envPath)) {
            include($envPath);
        } elseif (file_exists($defaultPath)) {
            include($defaultPath);
        } else {
            throw new Exception('The configuration file database.php does not exist.');
        }

        if (!isset($db) || !is_array($db) || empty($db)) {
            throw new Exception('No database connection settings were found in the database config file.');
        }

        $active_group = $params ?: ($active_group ?? null);
        if (empty($active_group) || !isset($db[$active_group])) {
            throw new Exception('You have specified an invalid database connection group.');
        }

        $params = $db[$active_group];
    } elseif (is_string($params)) {
        // Parse DSN
        $dns = @parse_url($params);
        if ($dns === false) {
            throw new Exception('Invalid DB Connection String');
        }

        $params = [
            'dbdriver' => $dns['scheme'] ?? '',
            'hostname' => isset($dns['host']) ? rawurldecode($dns['host']) : '',
            'username' => isset($dns['user']) ? rawurldecode($dns['user']) : '',
            'password' => isset($dns['pass']) ? rawurldecode($dns['pass']) : '',
            'database' => isset($dns['path']) ? rawurldecode(ltrim($dns['path'], '/')) : '',
        ];

        // Additional config items
        if (isset($dns['query'])) {
            parse_str($dns['query'], $extra);
            foreach ($extra as $key => $val) {
                if (strcasecmp($val, 'TRUE') === 0) {
                    $val = true;
                } elseif (strcasecmp($val, 'FALSE') === 0) {
                    $val = false;
                }
                $params[$key] = $val;
            }
        }
    }

    if (empty($params['dbdriver'])) {
        throw new Exception('You have not selected a database type to connect to.');
    }

    // Load DB classes
    require_once(BASEPATH . 'database/DB_driver.php');
    $useActiveRecord = $active_record_override ?? true;

    if ($useActiveRecord) {
        require_once(BASEPATH . 'database/DB_active_rec.php');
        if (!class_exists('CI_DB', false)) {
            class_alias('CI_DB_active_record', 'CI_DB');
        }
    } else {
        if (!class_exists('CI_DB', false)) {
            class_alias('CI_DB_driver', 'CI_DB');
        }
    }

    require_once(BASEPATH . 'database/drivers/' . $params['dbdriver'] . '/' . $params['dbdriver'] . '_driver.php');
    $driverClass = 'CI_DB_' . $params['dbdriver'] . '_driver';

    if (!class_exists($driverClass)) {
        throw new Exception("Database driver class {$driverClass} not found.");
    }

    $DB = new $driverClass($params);

    if (!empty($DB->autoinit)) {
        $DB->initialize();
    }

    if (!empty($params['stricton'])) {
        $DB->query('SET SESSION sql_mode="STRICT_ALL_TABLES"');
    }

    return $DB;
}
