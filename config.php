<? namespace claremcr\clareevents;

use claremcr\clareevents\classes\database;
use \PDOException;
use Katzgrau\KLogger\Logger;
use Psr\Log\LogLevel;
require 'vendor/autoload.php';
/**
 * config.php
 *
 * Includes a series of variables and functions some of which need to
 * be refactored into other files and classes, which handle some of the
 * base functions of the events booker.
 *
 * @author James Clemence <james@jvc26.org>
 *
 */




# Variables which tell the application where to look
require_once('/societies/claremcr/mcrpwd.php');
$my_pre = "mcrevents_";

define("DB_HOST", "localhost");
define("DB_USER", "claremcr");
define("DB_NAME", "claremcr");


# Create the logger
$logger = new Logger(__DIR__.'/logs/',LogLevel::WARNING, array (
	'extension' => 'log', // changes the log file extension
));
$logger->info('Logger Created'); // Will be NOT logged

# Make the dbh
try {
	$logger->debug("Creating Database"); // Will be NOT logged
	$dbh = new classes\database();
    $logger->info("Database created");
} catch(PDOException $e) {
	$logger->error("error creating db");
    $logger->error($e->getMessage());
    die();
}
# Validation functions

# Set the error level for E_USER_ERROR
set_error_handler("user_warning_error", E_USER_ERROR);

?>
