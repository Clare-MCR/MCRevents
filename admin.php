<?php

namespace claremcr\clareevents;

/**
 * admin.php
 *
 * Administrative interface for the events booker. Again this draws on the
 * class lib and config files to provide classes and functions for its
 * work. Some more refactoring is due on this file too.
 *
 * @author James Clemence <james@jvc26.org>
 *
 */

use claremcr\clareevents\classes;
use DateTime;
use PHPMailer;
use function claremcr\clareevents\functions\get_event_date;
use function claremcr\clareevents\functions\get_event_name;
use function claremcr\clareevents\functions\is_locked;
use function claremcr\clareevents\functions\validate_is_number;


error_reporting( E_ALL );
session_unset();

#include( 'class_lib.php' );
require_once( "config.php" );
global $logger;
$logger->info( "Admin.PHP called" );
# First do some User checks.

# Are we logged in with Raven?
if ( ! isset( $_SERVER['REMOTE_USER'] ) ) {
	trigger_error( "User is not logged in with Raven, something is wrong.", E_USER_ERROR );
}
# Initiate the new user object

$logger->info( "creating user" );
$user = new classes\user();
$logger->info( "user created continuing" );

# Get user info given the Raven crsid, if they don't exist, exit with error.
if ( $user->getFromCRSID( $_SERVER['REMOTE_USER'] ) == false ) {
	$logger->error( "Error creating user" );
	trigger_error( "User does not exist on this system. Please contact the administrators.", E_USER_ERROR );
}

# Check the user is not disabled
$logger->debug( "Checking User Permissions" );
if ( $user->has_perm( 'enabled' ) != true ) {
	$logger->error( "User Doesn't Have Permissions" );
	trigger_error( "User disabled, please contact administrators", E_USER_ERROR );
}
$logger->info( "User has permissions" );

# Check that the user has view permissions.
if ( ! $user->has_perm( 'e_view' ) ) {
	trigger_error( "User does not have view permissions on this system.", E_USER_ERROR );
}

# Check user has admin permissions
if ( $user->has_perm( 'e_adm' ) != true ) {
	if ( $user->has_perm( 's_adm' ) != true ) {
		trigger_error( "User is not an administrator user.", E_USER_ERROR );
	}
}

$logger->info( "User Has permissions" );
# User has view permissions, check that Allocator isn't running.
if ( is_locked() == 'Y' ) {
	trigger_error( "System Locked for Allocator Run, please try back later.", E_USER_ERROR );
}


# Let's run the lead in...
?>
    <!DOCTYPE html>
    <html>
    <head>
        <title> The Clare MCR Events Booking System Administrator Page</title>
        <meta http-equiv="Content-Type" content="text/html;charset=utf-8">
        <link type="text/css" rel="stylesheet" href="events.css">
        <!--[if lt IE 8]>
        <link type="text/css" rel="stylesheet" href="ie_old.css">
        <![endif]-->
        <script
                src="https://code.jquery.com/jquery-3.2.1.min.js"
                integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4="
                crossorigin="anonymous"></script>
        <script src="js/modernizr.min.js"></script>
        <script>Modernizr.load({
                test: Modernizr.inputtypes.time,
                nope: ['http://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css',
                    'https://cdnjs.cloudflare.com/ajax/libs/jquery-timepicker/1.10.0/jquery.timepicker.css',
                    'http://code.jquery.com/ui/1.12.1/jquery-ui.min.js',
                    'https://cdnjs.cloudflare.com/ajax/libs/jquery-timepicker/1.10.0/jquery.timepicker.js'],
                complete: function () {
                    $('input[type=time]').timepicker({
                        controlType: 'select',
                        timeFormat: 'hh:mm tt',
                        stepMinute: 15
                    });
                    $('input[type=date]').datepicker({
                        dateFormat: 'yy-mm-dd'
                    });

                }
            });
        </script>
    </head>
    <body>
    <div id="mainheader">
        <h1>Administrator Control Panel</h1>
    </div>

	<?php
	$logger->info( "laying out the welcome mat" );
	if ( $user->has_perm( 'e_adm' ) or $user->has_perm( 's_adm' ) ) {
		echo "<div id=\"user_welcome\">Welcome Administrator " . $user->getValue( 'name' ) . " <a href='mealbooker.py'>Go to Main</a><a href='admin.php'>Go to Admin</a></div>\n";
	}

	mainStuff( $user );
	?>

    <div class="footer">
        &copy; 2009-2017, Clare MCR
    </div>
    </body>
    </html>

	<?php
function mainStuff( $user ) {
	if ( isset( $_POST['new_event'] ) ) {
		newEventForm();
	} elseif ( isset( $_POST['edit_users'] ) ) {
		edit_user_form( $user );
	} elseif ( isset( $_POST['create_event'] ) ) {
		create_event();
	} elseif ( isset( $_POST['edit_event_form'] ) ) {
		editEventForm();
	} elseif ( isset( $_POST['edit_event'] ) ) {
		editEvent( $_POST['eventid'] );
	} elseif ( isset( $_POST['commit_edit_event'] ) ) {
		CommiteditEvent( $_POST['eventid'] );
	} elseif ( isset( $_POST['delete_event'] ) ) {
		deleteEventForm();
	} elseif ( isset( $_POST['remove_event'] ) ) {
		delete_event();
	} elseif ( isset( $_POST['sendevent'] ) ) {
		# Run the allocator before sending.
		#exec('./allocator.py', $output, $retvar);

//        if (!$retvar == 0) {
//            trigger_error("There is an error with the allocator run, please inform the administrator", E_USER_ERROR);
//        }

		send_guestlist( $_POST['eventid'] );
		echo "<br/>";
		send_billing( $_POST['eventid'] );
		mark_complete( $_POST['eventid'] );
	} elseif ( isset( $_POST['show_billing'] ) ) {
		show_billing( $_POST['eventid'] );
	} elseif ( isset( $_POST['show_guestlist'] ) ) {
		show_guestlist( $_POST['eventid'] );
	} elseif ( isset( $_POST['send_guestlist'] ) ) {
		send_guestlist( $_POST['eventid'] );
	} elseif ( isset( $_POST['send_billing'] ) ) {
		send_billing( $_POST['eventid'] );
	} elseif ( isset( $_POST['closebooking'] ) ) {
		close_booking( $_POST['eventid'] );
	} elseif ( isset( $_POST['reopenbooking'] ) ) {
		reopen_booking( $_POST['eventid'] );
	} elseif ( isset( $_POST['adduser'] ) ) {
		addusers();
	} elseif ( isset( $_POST['removeuser'] ) ) {
		removeusers();
	} elseif ( isset( $_POST['edituser'] ) ) {
		editusers();
	} elseif ( isset( $_POST['editDefaultForm'] ) ) {
		editDefaultForm();
	} elseif ( isset( $_POST['editDefault'] ) ) {
		editDefault();
	} else {
		greet();
		display_open();
		display_presend();
		display_outstanding();
	}
}

function greet() {
	echo "<h2>Admin Tasks</h2>\n";
	echo "<p>";
	echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
	echo "<input type=\"submit\" name=\"new_event\" value=\"Create New Event\">&nbsp;";
	echo "<input type=\"submit\" name=\"delete_event\" value=\"Delete an Event\">&nbsp;";
	echo "<input type=\"submit\" name=\"edit_users\" value=\"Edit User Access\">&nbsp;";
	echo "<input type=\"submit\" name=\"editDefaultForm\" value=\"Edit Default Values\">&nbsp;";
	echo "<input type=\"submit\" name=\"edit_event_form\" value=\"Edit Event\">";
	echo "</form>";
	echo "</p>";
}

function display_presend() {

	global $my_pre;
	global $dbh;

	echo "<hr/>";
	echo "<div class=\"admin_groupevents\">";
	echo "<h2>2. Send Guest and Billing Lists</h2>";

	$dbh->query( "SELECT * FROM " . $my_pre . "eventslist WHERE close_date < NOW() AND sent='N' ORDER BY event_date" );

	$result = $dbh->resultset();

	if ( count( $result ) > 0 ) {
		echo "<p>The following events have closed their booking but have outstanding notification.</p>";

		foreach ( $result as $event ) {
			echo "<h3 class=\"event_name\">" . $event['name'] . "<span class=\"date\">" . date( 'd/m/Y', strtotime( $event['event_date'] ) ) . "</span></h3>";
			echo "<table border=\"0\" cellspacing=\"0\" style=\"width:100%;\">";
			echo "<tr><th>Event Capacity</th><th>Event Date</th><th></th></tr>";
			echo "<tr><td>" . number_format( ( ( $event['current_guests'] / $event['total_guests'] ) * 100 ), 2 ) . "%</td><td>" . date( 'd/m/Y - H:i', strtotime( $event['event_date'] ) ) . "</td>";
			echo "<td><form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
			echo "<input type=\"hidden\" name=\"eventid\" value=\"" . $event['id'] . "\">";
			echo "<input type=\"submit\" name=\"sendevent\" value=\"Send Event\">";
			echo "</form>";
			echo "</td></tr>";
			echo "</table>";
		}
	} else {
		echo "<p>There are currently no events with outstanding notification.</p>";
	}
	echo "</div>";
}

function display_open() {

	global $my_pre;
	global $dbh;

	echo "<hr/>";
	echo "<div class=\"admin_groupevents\">";
	echo "<h2>1. Events Open for Booking</h2>";

	$dbh->query( "SELECT * FROM " . $my_pre . "eventslist WHERE open_date < NOW() AND close_date > NOW() ORDER BY event_date" );

	$result = $dbh->resultset();

	if ( count( $result ) > 0 ) {

		echo "<p>The following events are currently open for booking.</p>";

		foreach ( $result as $event ) {
			echo "<h3 class=\"event_name\">" . $event['name'] . "<span class=\"date\">" . date( 'd/m/Y', strtotime( $event['event_date'] ) ) . "</span></h3>";
			echo "<table class=\"event_table\" border=\"0\" cellspacing=\"0\">";
			echo "<tr><th>Event Capacity</th><th>Closing Date</th><th></th></tr>";

			$capacity = number_format( ( ( $event['current_guests'] / $event['total_guests'] ) * 100 ), 2 );

			echo "<tr><td>" . $capacity . "%</td>";
			echo "<td>" . date( 'd/m/Y - H:i', strtotime( $event['close_date'] ) ) . "</td>";
			echo "<td>";
			echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
			echo "<input type=\"hidden\" name=\"eventid\" value=\"" . $event['id'] . "\">";
			echo "<input type=\"submit\" name=\"closebooking\" value=\"Close Booking Now\">";
			echo "</form>";
			echo "</td></tr>";
			echo "</table>";
		}

	} else {
		echo "<p>There are currently no events with open bookings.</p>";
	}
	echo "</div>";
}

function reopen_booking( $eventid ) {

	global $my_pre;
	global $dbh;

	if ( ! preg_match( "/^[0-9]+$/", $_POST['time'] ) ) {
		trigger_error( 'Reopen time is non-numerical, please correct', E_USER_ERROR );
	}

	if ( ! preg_match( "/^[0-9]+$/", $eventid ) ) {
		trigger_error( "Eventid is non-numerical, please correct", E_USER_ERROR );
	}

	# Set the date for n hours in the future.

	$closetime = strtotime( "+ " . $_POST['time'] . " hour", time() );
	$datetime  = strftime( "%Y-%m-%d %H:%M:%S", $closetime );

	$dbh->query( "UPDATE " . $my_pre . "eventslist SET close_date=:closedate WHERE id=:eventid" );
	$dbh->bind( ":closedate", $datetime );
	$dbh->bind( ":eventid", $eventid );
	$dbh->execute();

	# As the guestlist may change, standby to resend.

	$dbh->query( "UPDATE " . $my_pre . "eventslist SET sent='N' WHERE id=:eventid" );
	$dbh->bind( ":eventid", $eventid );
	$dbh->execute();

	echo "<p>Booking Reopened Successfully.</p>";
	echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
	echo "<input type=\"submit\" value=\"Back to Admin\">";
	echo "</form>";

}

function close_booking( $eventid ) {

	global $my_pre;
	global $dbh;

	if ( ! preg_match( "/^[0-9]+$/", $eventid ) ) {
		trigger_error( "Eventid is non-numerical, please correct", E_USER_ERROR );
	}

	$dbh->query( "UPDATE " . $my_pre . "eventslist SET close_date=NOW(), sent='N' WHERE id=:eventid" );
	$dbh->bind( ":eventid", $eventid );
	$dbh->execute();

	echo "<p>Booking Closed Successfully</p>";
	echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
	echo "<input type=\"submit\" value=\"Back to Admin\">";
	echo "</form>";
}

function display_outstanding() {

	global $my_pre;
	global $dbh;

	echo "<hr/>";
	echo "<div class=\"admin_groupevents\">";
	echo "<h2>3. Closed Bookings, Events still pending</h2>";

	$dbh->query( "SELECT * FROM " . $my_pre . "eventslist WHERE close_date < NOW() AND event_date > NOW() AND sent='Y' ORDER BY event_date" );
	$result = $dbh->resultset();

	$num_events = $dbh->rowCount();

	if ( $num_events == 0 ) {
		echo "<p>There are currently no events between booking closure and occurrence.</p>";
	} else {
		echo "<p>Booking has closed for the following events, but the event has not yet taken place.</p>";
		for ( $j = 0; $j < $num_events; $j ++ ) {

			$capacity = number_format( ( ( $result[ $j ]['current_guests'] / $result[ $j ]['total_guests'] ) * 100 ), 2 );

			echo "<h3 class=\"event_name\">" . $result[ $j ]['name'] . "<span class=\"date\"> " . date( 'd/m/Y', strtotime( $result[ $j ]['event_date'] ) ) . "</span></h3>";
			echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
			echo "<input type=\"hidden\" name=\"eventid\" value=\"" . $result[ $j ]['id'] . "\">";
			echo "<table border=\"0\" class=\"event_table\">\n";
			echo "<tr><th>Event Capacity</th><th>Event Date</th><th></th></tr>\n";
			echo "<tr><td>" . $capacity . "%</td>";
			echo "<td>" . date( 'd/m/Y - H:i', strtotime( $result[ $j ]['event_date'] ) ) . "</td>";
			echo "<td><input type=\"submit\" name=\"reopenbooking\" value=\"Reopen Booking\"> ";
			echo "<select name=\"time\"></option>";
			echo "<option value=\"6\" selected>6 Hours</option>";
			echo "<option value=\"24\">24 Hours</option>";
			echo "<option value=\"48\">48 Hours</option>";
			echo "<option value=\"168\">1 Week</option>";
			echo "</select></td></tr>";
			echo "</table>";

			echo "<input type=\"submit\" name=\"show_guestlist\" value=\"See Guestlist\"> ";
			echo "<input type=\"submit\" name=\"show_billing\" value=\"See Billing\"> ";
			echo "<input type=\"submit\" name=\"send_guestlist\" value=\"Send Guestlist\"> ";
			echo "<input type=\"submit\" name=\"send_billing\" value=\"Send Billing\">";
			echo "</form>";
		}
	}
	echo "</div>";
}

function mark_complete( $eventid ) {

	global $my_pre;
	global $dbh;

	if ( ! preg_match( "/^[0-9]+$/", $eventid ) ) {
		trigger_error( "Event Id is non numerical, please fix.", E_USER_ERROR );
	}

	$dbh->query( "UPDATE " . $my_pre . "eventslist SET sent='Y' WHERE id=:eventid" );
	$dbh->bind( ":eventid", $eventid );
	$dbh->execute();

}

function show_guestlist( $eventid ) {

	global $my_pre;
	global $dbh;

	if ( ! preg_match( "/^[0-9]+$/", $eventid ) ) {
		trigger_error( "Event Id is non numerical, please fix.", E_USER_ERROR );
	}

	$dbh->query( "SELECT * FROM " . $my_pre . "booking_details WHERE eventid=:eventid ORDER BY booker,type DESC, id" );
	$dbh->bind( ":eventid", $eventid );

	$result = $dbh->resultset();

	echo "<h1>Official Guestlist</h1>\n";
	echo "<p>This is the official guestlist for the following event:</p>\n";
	echo "<p>Name: " . get_event_name( $eventid ) . "<br/>\n";
	echo "Date: " . date( 'd/m/Y', strtotime( get_event_date( $eventid ) ) ) . "</p>\n";
	echo "<hr/>";
	echo "<table border=\"0\" class=\"event_table guest_list\">";
	echo "<tr><th>#</th><th>Name</th><th>Ticket ID</th><th>Booker</th><th>Diet</th><th>Other Requirements</th></tr>";
	$j = 0;
	foreach ( $result as $value ) {
		$j ++;
		echo "<tr>";
		echo "<td>" . ( $j ) . "</td>";
		echo "<td>" . $value['name'] . "</td>";
		echo "<td>" . $value['id'] . "</td>";
		echo "<td>" . $value['booker'] . "</td>";
		echo "<td>" . $value['diet'] . "</td>";
		echo "<td>" . $value['other'] . "</td></tr>";
	}
	echo "</table> ";

	echo "<hr/>";
	echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
	echo "<input type=\"hidden\" name=\"eventid\" value=\"" . $eventid . "\">";
	echo "<input type=\"submit\" name=\"\" value=\"Back to Admin\"> ";
	echo "<input type=\"submit\" name=\"send_guestlist\" value=\"Send Guestlist\"> ";
	echo "</form>";
}

function show_billing( $eventid ) {

	global $my_pre;
	global $dbh;

	if ( ! preg_match( "/^[0-9]+$/", $eventid ) ) {
		trigger_error( "Event Id is non numerical, please fix.", E_USER_ERROR );
	}

	$dbh->query( "SELECT cost_normal,cost_second,event_date FROM " . $my_pre . "eventslist WHERE id=:id" );
	$dbh->bind( ":id", $eventid );
	$result       = $dbh->single();
	$full_price   = 0;
	$second_price = 0;
	$cost_normal  = $result['cost_normal'];
	$cost_second  = $result['cost_second'];

	echo "<h1>Billing Overview</h1>\n";
	echo "<p>This is the official billing list for the following event:</p>\n";
	echo "<p>Name: " . get_event_name( $eventid ) . "<br/>\n";
	echo "Date: " . date( 'd/m/Y', strtotime( $result['event_date'] ) ) . "<br/>\n";
	echo "Full price ticket: &#163;" . $cost_normal . "<br/>\n";
	echo "Second ticket: &#163;" . $cost_second . "</p>\n";
	echo "<hr/>";

	$dbh->query( "SELECT booker, SUM(tickets) AS tot_tickets FROM " . $my_pre . "booking WHERE eventid=:id AND admin=1 GROUP BY eventid" );
	$dbh->bind( ":id", $eventid );

	$result = $dbh->resultset();

	echo "<table border=\"0\" class=\"event_table billing_list\">";
	echo "<tr><th>Booker CRSid</th><th>Total Tickets</th><th>Full Price</th><th>Second Price</th><th>Money Owed(&#163;)</th></tr>";

	foreach ( $result as $booking ) {

		# Logic for working out the various ticket numbers and costs

		$full_price   = $booking['tot_tickets'];
		$second_price = 0;

		$money = number_format( ( $full_price * $cost_normal ) + ( $second_price * $cost_second ), 2 );

		# And the printout to the table

		echo "<tr><td>" . $booking['booker'] . " - MCR ADMIN</td>";
		echo "<td>" . $booking['tot_tickets'] . "</td>";
		echo "<td>" . $full_price . "</td>";
		echo "<td>" . $second_price . "</td>";
		echo "<td>" . $money . "</td></tr>";

	}

	$dbh->query( "SELECT booker, SUM(tickets) AS tot_tickets FROM " . $my_pre . "booking WHERE eventid=:id AND admin=0 GROUP BY booker" );
	$dbh->bind( ":id", $eventid );

	$result = $dbh->resultset();

	foreach ( $result as $booking ) {

		# Logic for working out the various ticket numbers and costs

		if ( $booking['tot_tickets'] == 1 ) {
			$full_price   = 1;
			$second_price = 0;
		} elseif ( $booking['tot_tickets'] > 1 ) {
			$full_price   = 1;#$booking['tot_tickets'] - 1;
			$second_price = $booking['tot_tickets'] - 1;#1;
		} else {
			trigger_error( "We have an issue, no-one should have < 1 tickets; the tot_tickets variable is: " . $booking['tot_tickets'] . " ", E_USER_ERROR );
		}

		$money = number_format( ( $full_price * $cost_normal ) + ( $second_price * $cost_second ), 2 );

		$booker = new classes\user();
		$booker->getFromCRSID( $booking['booker'] );

		$name = $booker->getValue( 'name' );

		# If the user isn't in lookup.cam, get their name from the first ticket.

		if ( $booker->getValue( 'name' ) == $booker->getValue( 'crsid' ) ) {

			$dbh->query( "SELECT name FROM " . $my_pre . "booking_details WHERE bookingid=:id AND booker=:booker AND eventid=:eventid AND type='1'" );
			$dbh->bind( ":id", $booking['bookingid'] );
			$dbh->bind( ":booker", $booking['booker'] );
			$dbh->bind( ":eventid", $eventid );


			$result_n = $dbh->single();

			$name = $result_n['name'];
		}


		# And the printout to the table

		echo "<tr><td>" . $booking['booker'] . " - " . $name . "</td>";
		echo "<td>" . $booking['tot_tickets'] . "</td>";
		echo "<td>" . $full_price . "</td>";
		echo "<td>" . $second_price . "</td>";
		echo "<td>" . $money . "</td></tr>";

	}

	echo "</table>";
	echo "<hr/>";
	echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
	echo "<input type=\"hidden\" name=\"eventid\" value=\"" . $eventid . "\">";
	echo "<input type=\"submit\" name=\"\" value=\"Back to Admin\"> ";
	echo "<input type=\"submit\" name=\"send_billing\" value=\"Send Billing\"> ";
	echo "</form>";
}

function send_guestlist( $eventid ) {

	global $my_pre;
	global $dbh;
	global $user;
	global $logger;

	$logger->info( "sending guestlist" );
	$email = new PHPMailer;
	$email->setFrom( 'mcr-socsec@clare.cam.ac.uk', 'Clare MCR Social Secretary' );
	$email->addAddress( $_SERVER['REMOTE_USER'] . '@cam.ac.uk', $user->getValue( 'name' ) );     // Add a recipient
	$email->isHTML( false );                                  // Set email format to plain text
	$email->CharSet = 'UTF-8';

	if ( ! preg_match( "/^[0-9]+$/", $eventid ) ) {
		trigger_error( "Event Id is non numerical, please fix.", E_USER_ERROR );
	}

	$logger->debug( "getting event info" );
	$dbh->query( "SELECT * FROM " . $my_pre . "booking_details WHERE eventid=:eventid ORDER BY booker,type DESC, id" );
	$dbh->bind( ":eventid", $eventid );

	$result = $dbh->resultset();
	$date   = date( 'd-m-Y', strtotime( get_event_date( $eventid ) ) );

	$logger->debug( "writing email body" );
	$body = "This is the official guestlist for the following event:\n\n ";
	$body = $body . "Name: " . get_event_name( $eventid ) . "\n";
	$body = $body . "Date: " . $date . "\r\n------------------------------\r\n\n";

	$logger->debug( "compiling csv" );
	$csv = "TicketID, Name, Booker, Diet, Other\r\n";
	foreach ( $result as $value ) {
		$csv = $csv . $value['id'] . ",";
		$csv = $csv . $value['name'] . ",";
		$csv = $csv . $value['booker'] . ",";
		$csv = $csv . $value['diet'] . ",";
		$csv = $csv . "\"" . $value['other'] . "\"\r\n";
	}
	$logger->debug( "Writing email" );
	$logger->debug( "Writing subject" );

	$email->Subject = "MCR Event Booker - Guestlist for Event \"" . get_event_name( $eventid ) . "\"";
	$logger->debug( "Writing body" );
	$email->Body = $body;
	$logger->debug( "Attaching csv" );
	$email->addStringAttachment( $csv, $date . "-GuestList.csv", 'base64', 'text/csv' );
	$logger->debug( "sending email" );
	if ( ! $email->send() ) {
		echo 'Message could not be sent.';
		$logger->error( 'Mailer Error: ', $email->ErrorInfo );
	} else {
		echo "A comma-separated list has been sent to your address for your records.";
	}
	$logger->debug( "sending guestlist [Done]" );

	echo "<hr/>";
	echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
	echo "<input type=\"submit\" value=\"Back to Admin\">";
	echo "</form>";
}

function send_billing( $eventid ) {

	global $my_pre;
	global $dbh;
	global $user;
	global $logger;

	$email = new PHPMailer;
	$email->setFrom( 'mcr-socsec@clare.cam.ac.uk', 'Clare MCR Social Secretary' );
	$email->addAddress( $_SERVER['REMOTE_USER'] . '@cam.ac.uk', $user->getValue( 'name' ) );     // Add a recipient
	$email->isHTML( false );                                  // Set email format to plain text
	$email->CharSet = 'UTF-8';

	if ( ! preg_match( "/^[0-9]+$/", $eventid ) ) {
		trigger_error( "Event Id is non numerical, please fix.", E_USER_ERROR );
	}

	# Get the costs for the eventid in question

	$dbh->query( "SELECT cost_normal,cost_second,event_date FROM " . $my_pre . "eventslist WHERE id=:id" );
	$dbh->bind( ":id", $eventid );
	$result = $dbh->single();

	$full_price   = 0;
	$second_price = 0;
	$cost_normal  = $result['cost_normal'];
	$cost_second  = $result['cost_second'];
	$date         = date( 'd-m-Y', strtotime( $result['event_date'] ) );
	# Prepare the email variables
	$body = "This is the official billing lists for the following event:\n\n ";
	$body = $body . "Name: " . get_event_name( $eventid ) . "\n";
	$body = $body . "Date: " . $date . "\n";
	$body = $body . "Full price ticket: £" . $cost_normal . "\r\n";
	$body = $body . "Second ticket: £" . $cost_second . "\r\n\n";
	$body = $body . "-----------------------------------\r\n";
	$body = $body . "Please pass the billing list on to the bursary and the non-College Billing List onto the Treasurer.\r\n";
	$body = $body . "-----------------------------------\r\n\n";

	# Collect the number of tickets for admin bookings

	$dbh->query( "SELECT SUM(tickets) AS tot_tickets FROM " . $my_pre . "booking WHERE eventid=:id AND admin=1 GROUP BY eventid" );
	$dbh->bind( ":id", $eventid );
	$result = $dbh->resultset();

	$csv1 = "Booker,CRSid,Total Tickets,Number Full Price,Number Second Price,Money Owed(\243)\r\n";

	foreach ( $result as $booking ) {
		# If we have any admin bookings, do the following:
		# Admin bookings - logic to work out #s @full price, and second price

		$full_price   = $booking['tot_tickets'];
		$second_price = 0;

		$money = number_format( ( $full_price * $cost_normal ) + ( $second_price * $cost_second ), 2 );

		# And write out to the email

		$csv1 = $csv1 . "MCR,MCR COMMITTEE," . $booking['tot_tickets'] . "," . $full_price . "," . $second_price . "," . $money . "\r\n";
	}

	# Collect number of tickets for normal bookings

	$dbh->query( "SELECT id AS bookingid, booker, SUM(tickets) AS tot_tickets FROM " . $my_pre . "booking WHERE eventid=:id AND admin=0 GROUP BY booker" );
	$dbh->bind( ":id", $eventid );

	$result = $dbh->resultset();

	foreach ( $result as $booking ) {

		# Logic for working out the various ticket numbers and costs

		if ( $booking['tot_tickets'] >= 1 ) {
			$full_price   = 1;
			$second_price = $booking['tot_tickets'] - 1;
		} else {
			trigger_error( "We have an issue, no-one should have < 1 tickets", E_USER_ERROR );
		}

		$money = number_format( ( $full_price * $cost_normal ) + ( $second_price * $cost_second ), 2 );

		# Now add the booking details to the billing sheet

		# LDAP lookup for the MCR Member's Name - for the Bursary's info.

		$booker = new classes\user();
		$booker->getFromCRSID( $booking['booker'] );

		if ( ! ( $booker->getValue( 'college_bill' ) ) ) {
			$crsid = "MCR";
			$name  = "MCR COMMITTEE";
		} else {

			$name = $booker->getValue( 'name' );

			# If the user has taken themselves out of the LDAP lookup, get it from the PRIMARY TICKET for this event
			if ( $booker->getValue( 'name' ) == $booker->getValue( 'crsid' ) ) {

				$dbh->query( "SELECT name FROM " . $my_pre . "booking_details WHERE bookingid=:id AND booker=:booker AND eventid=:eventid AND type='1'" );
				$dbh->bind( ":id", $booking['bookingid'] );
				$dbh->bind( ":booker", $booking['booker'] );
				$dbh->bind( ":eventid", $eventid );

				$result_n = $dbh->single();
				$name     = $result_n['name'];
			}
			$crsid = $booking['booker'];
		}
		$csv1 = $csv1 . $crsid . ",";
		$csv1 = $csv1 . $name . ",";
		$csv1 = $csv1 . $booking['tot_tickets'] . ",";
		$csv1 = $csv1 . $full_price . ",";
		$csv1 = $csv1 . $second_price . ",";
		$csv1 = $csv1 . $money . "\r\n";
	}

	# Collect number of tickets for normal bookings

	$dbh->query( "SELECT id AS bookingid, booker, SUM(tickets) AS tot_tickets FROM " . $my_pre . "booking WHERE eventid=:id AND admin=0 GROUP BY booker" );
	$dbh->bind( ":id", $eventid );

	$result = $dbh->resultset();
	$csv2   = "Booker,CRSid,Total Tickets,Number Full Price,Number Second Price,Money Owed(\243)\r\n";

	foreach ( $result as $booking ) {

		# Logic for working out the various ticket numbers and costs

		if ( $booking['tot_tickets'] >= 1 ) {
			$full_price   = 1;
			$second_price = $booking['tot_tickets'] - 1;
		} else {
			trigger_error( "We have an issue, no-one should have < 1 tickets", E_USER_ERROR );
		}

		$money = number_format( ( $full_price * $cost_normal ) + ( $second_price * $cost_second ), 2 );

		# Now add the booking details to the billing sheet

		# LDAP lookup for the MCR Member's Name - for the Bursary's info.

		$booker = new classes\user();
		$booker->getFromCRSID( $booking['booker'] );

		if ( $booker->getValue( 'college_bill' ) ) {
			continue;
		}

		$name = $booker->getValue( 'name' );

		# If the user has taken themselves out of the LDAP lookup, get it from the PRIMARY TICKET for this event
		if ( $booker->getValue( 'name' ) == $booker->getValue( 'crsid' ) ) {

			$dbh->query( "SELECT name FROM " . $my_pre . "booking_details WHERE bookingid=:id AND booker=:booker AND eventid=:eventid AND type='1'" );
			$dbh->bind( ":id", $booking['bookingid'] );
			$dbh->bind( ":booker", $booking['booker'] );
			$dbh->bind( ":eventid", $eventid );

			$result_n = $dbh->single();
			$name     = $result_n['name'];
		}

		$csv2 = $csv2 . $booking['booker'] . ",";
		$csv2 = $csv2 . $name . ",";
		$csv2 = $csv2 . $booking['tot_tickets'] . ",";
		$csv2 = $csv2 . $full_price . ",";
		$csv2 = $csv2 . $second_price . ",";
		$csv2 = $csv2 . $money . "\r\n";
	}


	$email->Subject = "MCR Event Booker - Billing Lists for Event \"" . get_event_name( $eventid ) . "\"";
	$email->Body    = $body;

	$email->addStringAttachment( $csv1, $date . "-BilingList.csv", 'base64', 'text/csv' );
	$email->addStringAttachment( $csv2, $date . "-NonCollegeBilingList.csv", 'base64', 'text/csv' );

	if ( ! $email->send() ) {
		echo 'Message could not be sent.';
		$logger->error( 'Mailer Error: ', $email->ErrorInfo );
	} else {
		echo "Two comma-separated lists have been sent to your address, please forward a copy of the billingList to the bursary.";
	}

	echo "";
	echo "<hr/>";
	echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
	echo "<input type=\"submit\" value=\"Back to Admin\">";
	echo "</form>";

}


function newEventForm() {

	global $dbh;
	global $my_pre;

	$def_total_guests = 0;
	$def_max_guests   = 0;
	$def_main_price   = 0;
	$def_guest_price  = 0;
	$monthnos         = array(
		1  => 'January',
		2  => 'February',
		3  => 'March',
		4  => 'April',
		5  => 'May',
		6  => 'June',
		7  => 'July',
		8  => 'August',
		9  => 'September',
		10 => 'October',
		11 => 'November',
		12 => 'December'
	);

	$timenos = array(
		'Midnight' => '00:00:00',
		'9AM'      => '09:00:00',
		'10AM'     => '10:00:00',
		'11AM'     => '11:00:00',
		'Midday'   => '12:00:00',
		'1PM'      => '13:00:00',
		'2PM'      => '14:00:00',
		'3PM'      => '15:00:00',
		'4PM'      => '16:00:00',
		'5PM'      => '17:00:00',
		'6PM'      => '18:00:00',
		'7PM'      => '19:00:00',
		'8PM'      => '20:00:00',
		'9PM'      => '21:00:00',
		'10PM'     => '22:00:00',
		'11PM'     => '23:00:00'
	);

	# define a few default variables, should probably do this somewhere else at some point...

	# Get the default values from the database

	$dbh->query( "SELECT name,value FROM " . $my_pre . "defaults" );
	$result = $dbh->resultset();

	# Set those which are stored in the defaults table

	foreach ( $result as $var ) {
		if ( $var['name'] == 'cost_normal' ) {
			$def_main_price = $var['value'];
		} elseif ( $var['name'] == 'cost_second' ) {
			$def_guest_price = $var['value'];
		} elseif ( $var['name'] == 'max_guests' ) {
			$def_max_guests = $var['value'];
		} elseif ( $var['name'] == 'total_guests' ) {
			$def_total_guests = $var['value'];
		}
	}

	echo "<h1>Create New Event</h1>\n";
	echo "<h3>Event Details</h3>";
	echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
	echo "<dl>";
	echo "<dt>Event name</dt>";
	echo "<dd><input type=\"text\" name=\"event_name\" required placeholder=\"e.g. MCR Formal\" title=\"Please enter event name. The event date will automatically be added.\" size=\"30\" maxlength=\"50\"></dd>";
	echo "<dt>Total number of available tickets</dt>";
	echo "<dd><input type=\"number\" min=\"0\" size=\"5\" name=\"total_guests\" value=\"" . $def_total_guests . "\" required></dd>";
	echo "<dt>Maximum number of guests each person can bring</dt>";
	echo "<dd><input type=\"number\" min=\"0\" size=\"5\" name=\"max_guests\" value=\"" . $def_max_guests . "\" required></dd>";

	echo "<dt>Enter the date for the event</dt>";
	# Date picking for the event date
	echo "<dd>";
	echo "<select name=\"event_day\">";
	echo "<option value=\"---\">---</option>";
	for ( $j = 1; $j <= 31; $j ++ ) {
		if ( $j == date( 'j' ) ) {
			echo "<option value=$j selected>$j</option>";
		} else {
			echo "<option value=$j>$j</option>";
		}
	}
	echo "</select>";
	echo "<select name=\"event_month\">";
	echo "<option value=\"---\">---</option>";
	for ( $j = 1; $j <= count( $monthnos ); $j ++ ) {
		if ( $j == date( 'n' ) ) {
			echo "<option value=$j selected>$monthnos[$j]</option>";
		} else {
			echo "<option value=$j>$monthnos[$j]</option>";
		}
	}
	echo "</select>";
	echo "<select name=\"event_year\">";
	for ( $i = date( 'Y' ); $i <= ( date( 'Y' ) + 1 ); $i ++ ) {
		echo "<option value=$i>$i</option>";
	}
	echo "</select>";
	echo " at ";
	echo "<select name=\"event_hour\">";
	for ( $j = 1; $j <= 24; $j ++ ) {
		if ( $j == 19 ) {
			echo "<option value=\"$j\" selected>$j</option>";
		} else {
			echo "<option value=\"$j\">$j</option>";
		}
	}
	echo "</select>";
	echo "<select name=\"event_minute\">";
	echo "<option value=\"00\" selected>00</option>";
	echo "<option value=\"15\">15</option>";
	echo "<option value=\"30\">30</option>";
	echo "<option value=\"45\">45</option>";
	echo "</select>";
	echo "</dd></dl>";

	echo "<h3>Pricing</h3>";
	echo "<dl>";
	echo "<dt>Price for a standard ticket</dt>";
	echo "<dd>&pound; <input type=\"text\" name=\"standard_price\" size=\"5\" value=\"" . $def_main_price . "\" required pattern=\"\d+\.\d\d\" title=\"Enter price in pounds and pence, e.g. 7.50\"></dd>";
	echo "<dt>Price for first guest ticket</dt>";
	echo "<dd>&pound; <input type=\"text\" name=\"second_price\" size=\"5\" value=\"" . $def_guest_price . "\" required pattern=\"\d+\.\d\d\" title=\"Enter price in pounds and pence, e.g. 7.50\"></dd>";
	echo "</dl>";

	echo "<h3>Guest Type</h3>";
	echo "<input type=\"hidden\" name=\"guest_type[mcr_member]\" value=\"0\">";
	echo "<input type=\"checkbox\" name=\"guest_type[mcr_member]\" value=\"1\" checked>MCR Members";
	echo "<input type=\"hidden\" name=\"guest_type[associate]\" value=\"0\">";
	echo "<input type=\"checkbox\" name=\"guest_type[associate]\" value=\"1\" checked>Clare Associate Members";
	echo "<input type=\"hidden\" name=\"guest_type[cra]\" value=\"0\">";
	echo "<input type=\"checkbox\" name=\"guest_type[cra]\" value=\"1\" checked>CRAs";

	echo "<h3>Booking Details</h3>";
	echo "<div class=\"note\">";
	echo "<p>If the below checkbox is selected, the system will resort to using the default times of opening at 6pm five days previous to the event, and closing 9am two days before. For a regular formal on Friday, this will open at 6pm on Sunday and close at 9am on the Wednesday before.</p>";
	echo "<p><input type=\"checkbox\" id=\"default_openclose\" name=\"default_openclose\" onchange=\"toggleDetails()\" /><label for=\"default_openclose\"> Use default open and close times</label></p>";
	echo "</div>";

	echo "<dl id=\"bookingdetails\">";
	echo "<dt>Booking opens at the following time &amp; date:</dt>";
	echo "<dd>";
	# Table for the booking start and end dates.
	echo "<select name=\"open_day\">";
	echo "<option value=\"---\">---</option>";
	for ( $j = 1; $j <= 31; $j ++ ) {
		if ( $j == date( 'j' ) ) {
			echo "<option value=$j selected>$j</option>";
		} else {
			echo "<option value=$j>$j</option>";
		}
	}
	echo "</select>";

	echo "<select name=\"open_month\">";
	echo "<option value=\"---\">---</option>";
	for ( $j = 1; $j <= count( $monthnos ); $j ++ ) {
		if ( $j == date( 'n' ) ) {
			echo "<option value=$j selected>$monthnos[$j]</option>";
		} else {
			echo "<option value=$j>$monthnos[$j]</option>";
		}
	}
	echo "</select>";
	echo "<select name=\"open_year\">";
	for ( $i = date( 'Y' ); $i <= ( date( 'Y' ) + 1 ); $i ++ ) {
		echo "<option value=$i>$i</option>";
	}
	echo "</select>";
	echo " at ";
	echo "<select name=\"open_time\">";
	echo "<option value=\"---\">---</option>";
	foreach ( $timenos as $word => $time ) {
		echo "<option value=$time>$word</option>";
	}
	echo "</select>";
	echo "</dd>";

	# And the close booking date
	echo "<dt>Booking closes at the following time &amp; date:</dt>";
	echo "<dd>";
	echo "<select name=\"close_day\">";
	echo "<option value=\"---\">---</option>";
	for ( $j = 1; $j <= 31; $j ++ ) {
		if ( $j == date( 'j' ) ) {
			echo "<option value=$j selected>$j</option>";
		} else {
			echo "<option value=$j>$j</option>";
		}
	}
	echo "</select>";

	echo "<select name=\"close_month\">";
	echo "<option value=\"---\">---</option>";
	for ( $j = 1; $j <= count( $monthnos ); $j ++ ) {
		if ( $j == date( 'n' ) ) {
			echo "<option value=$j selected>$monthnos[$j]</option>";
		} else {
			echo "<option value=$j>$monthnos[$j]</option>";
		}
	}
	echo "</select>";
	echo "<select name=\"close_year\">";
	for ( $i = date( 'Y' ); $i <= ( date( 'Y' ) + 1 ); $i ++ ) {
		echo "<option value=$i>$i</option>";
	}
	echo "</select>";
	echo " at ";
	echo "<select name=\"close_time\">";
	echo "<option value=\"---\">---</option>";

	foreach ( $timenos as $word => $time ) {
		echo "<option value=$time>$word</option>";
	}
	echo "</select>";
	echo "</dd>";
	echo "</dl>";
	#ugly inline script; will move it to a centralized script file once I get round to it
	echo "<script type=\"text/javascript\">";
	echo "function toggleDetails() { details=document.getElementById('bookingdetails'); checkbox=document.getElementById('default_openclose'); checkbox.checked?details.style.display='none':details.style.display='block'; }";
	echo "</script>";

	# Echo a choice form for number of repeats - if any.

	echo "<h3>Repeating Events</h3>\n";
	echo "<p>Repeat ";
	echo "<select name=\"interval\">";
	echo "<option value=\"DAY\">daily</option>";
	echo "<option value=\"WEEK\" selected>weekly</option>";
	echo "<option value=\"MONTH\">monthly</option>";
	echo "<option name=\"YEAR\">yearly</option>";
	echo "</select> for a further ";
	echo "<input type=\"number\" name=\"norepeats\" size=\"5\" min=\"0\" value=\"0\"> times.";
	echo "</p>\n";

	echo "<hr/>";

	echo "<table border=\"0\" cellspacing=\"0\">";
	echo "<tr><td><input type=\"submit\" name=\"create_event\" value=\"Create Event\"></td>";
	echo "<td><input type=\"submit\" value=\"Return to Admin\"></td>";
	echo "</tr></table>";
	echo "</form>";
}

function create_event() {
	global $logger;
	$close_date = 0;

	$logger->debug( "checking POST", $_POST );
	# Validate the numerical inputs
	validate_is_number( $_POST['total_guests'], "Total Guests is not a number." );

	validate_is_number( $_POST['max_guests'], "Maximum guests is not a number." );

	if ( isset( $_POST['norepeats'] ) ) {
		validate_is_number( $_POST['norepeats'], "Number of repeats is not a number." );
	}

	# Validate the interval if set

	if ( isset( $_POST['interval'] ) ) {
		if ( ! preg_match( "/^(DAY|WEEK|MONTH|YEAR)$/", $_POST['interval'] ) ) {
			trigger_error( "Interval is not a valid value.", E_USER_ERROR );
		}
	}

	# Match the prices, if both are unset will count as a 'free event', second price can be unset.

	if ( isset( $_POST['standard_price'] ) ) {
		if ( ! preg_match( '/^[0-9]+\.[0-9]{2}$/', $_POST['standard_price'] ) ) {
			trigger_error( "Price is not given in pounds and pence", E_USER_ERROR );
		}
	}

	if ( isset( $_POST['second_price'] ) ) {
		if ( ! preg_match( '/^[0-9]+\.[0-9]{2}$/', $_POST['second_price'] ) ) {
			trigger_error( "Second price is not given in pounds and pence", E_USER_ERROR );
		}
	}

	# Validate the guest_type[] array:

	if ( $_POST['guest_type']['mcr_member'] == 0 ) {
		if ( $_POST['guest_type']['associate'] == 0 ) {
			if ( $_POST['guest_type']['cra'] == 0 ) {
				trigger_error( "You haven't allowed any guests to the event. Please go back and select a guest type.", E_USER_ERROR );
			}
		}
	}

	# Check that the numbers given for the event date are correct

	if ( ! preg_match( "/^[0-9]?[0-9]$/", $_POST['event_day'] ) ) {
		trigger_error( "Event day is not a valid number... please correct", E_USER_ERROR );
	}

	if ( ! preg_match( "/^[0-9]?[0-9]$/", $_POST['event_month'] ) ) {
		trigger_error( "Event month is not a valid number ... please correct", E_USER_ERROR );
	}

	if ( ! preg_match( "/^[0-9]{4}$/", $_POST['event_year'] ) ) {
		trigger_error( "Event year is not a valid number ... please correct", E_USER_ERROR );
	}

	# Check that the event date is a date

	if ( ! checkdate( $_POST['event_month'], $_POST['event_day'], $_POST['event_year'] ) ) {
		trigger_error( "Chosen event date does not exist", E_USER_ERROR );
	}

	# Check that event date is after now

	$event_check = $_POST['event_year'] . "-" . $_POST['event_month'] . "-" . $_POST['event_day'];
	if ( strtotime( date( "Y-m-d" ) ) >= strtotime( $event_check ) ) {
		trigger_error( "Event date has already passed", E_USER_ERROR );
	}

	# Check hours and minutes are numerical

	if ( ! preg_match( "/^[0-9]?[0-9]$/", $_POST['event_hour'] ) ) {
		trigger_error( "Event hour is not a valid number ... please correct", E_USER_ERROR );
	}

	if ( ! preg_match( "/^[0-9]?[0-9]$/", $_POST['event_minute'] ) ) {
		trigger_error( "Event minute is not a valid number ... please correct", E_USER_ERROR );
	}

	# if flag for default open/close time is set, skip the checking of the input dates and times
	if ( $_POST['default_openclose'] == false ) {

		# If a specified booking date has been given, check that
		if ( $_POST['open_day'] != '---' ) {
			if ( $_POST['close_day'] != '---' ) {
				if ( ! preg_match( "/^[0-9]?[0-9]$/", $_POST['open_day'] ) ) {
					trigger_error( "Open day is not a valid number... please correct", E_USER_ERROR );
				}

				if ( ! preg_match( "/^[0-9]?[0-9]$/", $_POST['open_month'] ) ) {
					trigger_error( "Open month is not a valid number ... please correct", E_USER_ERROR );
				}

				if ( ! preg_match( "/^[0-9]{4}$/", $_POST['open_year'] ) ) {
					trigger_error( "Open year is not a valid number ... please correct", E_USER_ERROR );
				}

				if ( ! preg_match( "/^[0-9]{2}:[0-9]{2}:[0-9]{2}$/", $_POST['open_time'] ) ) {
					trigger_error( "Open time is not a valid time ... please correct", E_USER_ERROR );
				}

				if ( ! checkdate( $_POST['open_month'], $_POST['open_day'], $_POST['open_year'] ) ) {
					trigger_error( "Chosen open date does not exist", E_USER_ERROR );
				}

				# If the dates are given and validate, set the open_date
				$open_date = $_POST['open_year'] . '-' . $_POST['open_month'] . '-' . $_POST['open_day'] . " " . $_POST['open_time'];

			} else {
				trigger_error( "Please provide a close date if you are providing an open date", E_USER_ERROR );
			}
		}

		if ( $_POST['close_day'] != '---' ) {
			if ( $_POST['open_day'] != '---' ) {
				if ( ! preg_match( "/^[0-9]?[0-9]$/", $_POST['close_day'] ) ) {
					trigger_error( "Close day is not a valid number... please correct", E_USER_ERROR );
				}

				if ( ! preg_match( "/^[0-9]?[0-9]$/", $_POST['close_month'] ) ) {
					trigger_error( "Close month is not a valid number ... please correct", E_USER_ERROR );
				}

				if ( ! preg_match( "/^[0-9]{4}$/", $_POST['close_year'] ) ) {
					trigger_error( "Close year is not a valid number ... please correct", E_USER_ERROR );
				}

				if ( ! checkdate( $_POST['close_month'], $_POST['close_day'], $_POST['close_year'] ) ) {
					trigger_error( "Chosen close date does not exist", E_USER_ERROR );
				}

				if ( ! preg_match( "/^[0-9]{2}:[0-9]{2}:[0-9]{2}$/", $_POST['close_time'] ) ) {
					trigger_error( "Close time is not a valid time ... please correct", E_USER_ERROR );
				}

				# If the dates are given and validate, set the close_date
				$close_date = $_POST['close_year'] . '-' . $_POST['close_month'] . '-' . $_POST['close_day'] . " " . $_POST['close_time'];

			} else {
				trigger_error( "Please provide an open day if you are providing a close date", E_USER_ERROR );
			}
		}
	}

	# Validate event name, include lots of things.

	if ( ! preg_match( '/^[\w\s-\.\']+$/', stripslashes( $_POST['event_name'] ) ) ) {
		trigger_error( "Eventname may include whitespace, alphanumeric characters, hyphens, periods and inverted commas only.", E_USER_ERROR );
	}

	# Once validated, escape the event name

	$eventname = stripslashes( $_POST['event_name'] );
	$eventname = htmlentities( $eventname, ENT_QUOTES );

	# Then create the correctly formatted event date

	$event_date = $_POST['event_year'] . '-' . $_POST['event_month'] . '-' . $_POST['event_day'] . " " . $_POST['event_hour'] . ":" . $_POST['event_minute'] . ":" . "00";


	# Apply defaults if not given, these default to opening 5 days before at 18:00 and closing 2 days before at 9am
	# For regular formals this corresponds to Sunday 18:00 and Wednesday 9:00 respectively
	if ( ! isset( $open_date ) ) {
		$open_date  = date( "Y-m-d", strtotime( $event_date . "-5 days" ) ) . " 18:00:00";
		$close_date = date( "Y-m-d", strtotime( $event_date . "-2 days" ) ) . " 09:00:00";

	}

	# Several Date Checks
	# - make sure open date is after now
	if ( strtotime( date( "Y-m-d" ) ) > strtotime( $open_date ) ) {
		trigger_error( "Open date is in the past!", E_USER_ERROR );
	}
	# - make sure close date is after open date
	if ( strtotime( $open_date ) > strtotime( $close_date ) ) {
		trigger_error( "Open date is before close date!", E_USER_ERROR );
	}
	# - make sure close date is before event
	if ( strtotime( $close_date ) > strtotime( $event_date ) ) {
		trigger_error( "Booking closes after event date!", E_USER_ERROR );
	}
	# the above implicitly checks if the open date is before the event date

	# Create the new event object

	$event = new classes\event();

	# And give it some variables
	$event->setValue( 'name', $eventname );
	$event->setValue( 'total_guests', $_POST['total_guests'] );
	$event->setValue( 'current_guests', 0 );
	$event->setValue( 'max_guests', $_POST['max_guests'] );
	$event->setValue( 'cost_normal', $_POST['standard_price'] );
	$event->setValue( 'cost_second', $_POST['second_price'] );
	$event->setValue( 'event_date', $event_date );
	$event->setValue( 'open_date', $open_date );
	$event->setValue( 'close_date', $close_date );
	$event->setValue( 'sent', 'N' );
	$logger->info( "Setting guest types" );
	$event->setValue( 'mcr_member', $_POST['guest_type']['mcr_member'] );
	$event->setValue( 'associate_member', $_POST['guest_type']['associate'] );
	$event->setValue( 'cra', $_POST['guest_type']['cra'] );

	$logger->debug( "values set" );
	# And commit its creation to the database
	$event->create();

	# And lets have some output...
	echo "<p>Congratulations, you have successfully created the following event:</p>";
	echo "<p><b>Event Name: </b>" . $eventname . "</p>";
	echo "<p><b>On the Date: </b>" . $event_date . "</p>";
	echo "<p><b>Total Guests: </b>" . $_POST['total_guests'] . "</p>";
	echo "<p><b>Maximum Guests for each Member: </b>" . $_POST['max_guests'] . "</p>";
	echo "<p><b>A standard ticket will cost:</b> &#163;" . $_POST['standard_price'] . "</p>";
	echo "<p><b>The second ticket will cost:</b> &#163;" . $_POST['second_price'] . "</p>";
	echo "<p><b>Booking will open on: </b>" . $open_date . "</p>";
	echo "<p><b>Booking will close on: </b>" . $close_date . "</p>";
	echo "<p>Yours, The MCR Booking system</p>";


	# Now, if norepeats and interval are set:

	if ( isset( $_POST['norepeats'] ) ) {
		if ( isset( $_POST['interval'] ) ) {

			# Create the repeating events.

			for ( $i = 1; $i <= $_POST['norepeats']; $i ++ ) {
				$name = $event->getValue( 'name' );

				# Get a PHP timestamp of the event_date
				$eventdate = strtotime( $event->getValue( 'event_date' ) );

				# Increment it by the given interval
				$interval  = "+ " . $i . " " . $_POST['interval'];
				$eventdate = strtotime( $interval, $eventdate );

				# Convert it back to a mysql DATETIME
				$eventdate = strftime( '%Y-%m-%d %H:%M:%S', $eventdate );

				# Repeat the process for the open and close dates:

				$opendate = strtotime( $event->getValue( 'open_date' ) );
				$opendate = strtotime( $interval, $opendate );
				$opendate = strftime( '%Y-%m-%d %H:%M:%S', $opendate );

				$closedate = strtotime( $event->getValue( 'close_date' ) );
				$closedate = strtotime( $interval, $closedate );
				$closedate = strftime( '%Y-%m-%d %H:%M:%S', $closedate );

				# Set up the new event

				$newevent = new classes\event();

				# Set up the variables

				$newevent->setValue( 'name', $name );
				$newevent->setValue( 'total_guests', $_POST['total_guests'] );
				$newevent->setValue( 'current_guests', 0 );
				$newevent->setValue( 'max_guests', $_POST['max_guests'] );
				$newevent->setValue( 'cost_normal', $_POST['standard_price'] );
				$newevent->setValue( 'cost_second', $_POST['second_price'] );
				$newevent->setValue( 'event_date', $eventdate );
				$newevent->setValue( 'open_date', $opendate );
				$newevent->setValue( 'close_date', $closedate );
				$newevent->setValue( 'sent', 'N' );


				foreach ( $_POST['guest_type'] as $guesttype => $value ) {
					$newevent->setValue( $guesttype, $value );
				}

				$newevent->create();

				# And let the user know whats happened
				echo "<hr/>";
				echo "<p>Congratulations, you have successfully created the following event:</p>";
				echo "<p><b>Event Name: </b>" . $name . "</p>";
				echo "<p><b>On the Date: </b>" . $eventdate . "</p>";
				echo "<p><b>Total Guests: </b>" . $_POST['total_guests'] . "</p>";
				echo "<p><b>Maximum Guests for each Member: </b>" . $_POST['max_guests'] . "</p>";
				echo "<p><b>A standard ticket will cost:</b> &#163;" . $_POST['standard_price'] . "</p>";
				echo "<p><b>The second ticket will cost:</b> &#163;" . $_POST['second_price'] . "</p>";
				echo "<p><b>Booking will open on: </b>" . $opendate . "</p>";
				echo "<p><b>Booking will close on: </b>" . $closedate . "</p>";
				echo "<p>Yours, The MCR Booking system</p>";

			}

		} else {
			trigger_error( "Both number of repeats and interval must be set to work.", E_USER_ERROR );
		}
	}
}

function deleteEventForm() {
	global $my_pre;
	global $dbh;

	$dbh->query( "SELECT * FROM " . $my_pre . "eventslist WHERE NOW() < event_date ORDER BY event_date" );
	$result = $dbh->resultset();

	echo "<h1>Delete Events</h1>\n";

	if ( count( $result ) > 0 ) {
		echo "<p>Please select an event to delete.</p>";
		echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
		foreach ( $result as $event ) {
			echo "<h3 class=\"event_name\"><input type=\"radio\" name=\"delete_selection\" id=\"event_" . $event['id'] . "\" value=\"" . $event['id'] . "\"> <label for=\"event_" . $event['id'] . "\">" . $event['name'] . "</label><span class=\"date\">" . date( 'd/m/Y', strtotime( $event['event_date'] ) ) . "</span></h3>\n";
			echo "<table border=\"0\" cellspacing=\"0\">";
			echo "<tr><td>Booking Opens:</td><td>" . date( 'd/m/Y - H:i', strtotime( $event['open_date'] ) ) . "</td></tr>";
			echo "<tr><td>Booking Closes:</td><td>" . date( 'd/m/Y - H:i', strtotime( $event['close_date'] ) ) . "</td></tr>";
			echo "<tr><td>Event Date:</td><td>" . date( 'd/m/Y - H:i', strtotime( $event['event_date'] ) ) . "</td></tr>";
			echo "</table>";
		}
		echo "<hr/>";
		echo "<input type=\"Submit\" name=\"remove_event\" value=\"Delete Selected Event\"> ";
		echo "<input type=\"submit\" value=\"Return to Admin\">";
		echo "</form>";
	} else {
		echo "<p>There are currently no events which are yet to happen.</p>";
		# Return to admin
		echo "<hr/>";
		echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
		echo "<input type=\"submit\" value=\"Return to Admin\">";
		echo "</form>";
	}


}

function delete_event() {
	global $my_pre;
	global $dbh;

	# Ensure that the delete selection is a number and a number only.

	if ( ! preg_match( "/^[0-9]+$/", $_POST['delete_selection'] ) ) {
		trigger_error( "Delete Selection is not a number, please correct", E_USER_ERROR );
	}

	# Get the event name

	$dbh->query( "SELECT name,event_date FROM " . $my_pre . "eventslist WHERE id=:eventid" );
	$dbh->bind( ":eventid", $_POST['delete_selection'] );
	$result = $dbh->single();

	$event_name = $result['name'];
	$event_date = date( 'd/m/Y', strtotime( $result['event_date'] ) );

	# And delete it

	$dbh->query( "DELETE FROM " . $my_pre . "eventslist WHERE id=:eventid" );
	$dbh->bind( ":eventid", $_POST['delete_selection'] );
	$dbh->execute();

	echo "<p>Event \"" . $event_name . "\" successfully deleted, thanks.</p>";

	# Then mail the administrator with confirmation

	echo "<p>Mailing admin " . $_SERVER['REMOTE_USER'] . " confirmation of deletion.</p>";

	$to      = $_SERVER['REMOTE_USER'] . "@cam.ac.uk";
	$subject = "MCR Event Booker - event \"" . $event_name . "\" DELETED";
	$body    = "Notification email to notify that event \"" . $event_name . "\" on " . $event_date . " has been deleted from the MCR Events booker.\r\n\n Yours, MCR Event Booker";

	mail( $to, $subject, $body, ( "Content-type: text/plain; charset=ISO-8859-1; format=flowed\r\nContent-Transfer-Encoding: quoted-printable" ) );

	# Tell the person at the screen to wait while emailing guests

	echo "<p>Mailing guests who were booked on this event, please wait...</p>";

	$dbh->query( "SELECT DISTINCT booker FROM " . $my_pre . "booking WHERE eventid=:eventid" );
	$dbh->bind( ":eventid", $_POST['delete_selection'] );

	$result = $dbh->resultset();

	foreach ( $result as $booker ) {

		$crsid = $booker['booker'];

		echo "<p>Mailing guest: " . $crsid . "@cam.ac.uk";

		$to      = $crsid . "@cam.ac.uk";
		$subject = "MCR Event Booker - event \"" . $event_name . "\" CANCELLED";
		$body    = "Dear MCR member,\r\n\n";
		$body    = $body . "It is with great regret that the MCR Committee notify you that event \"" . $event_name . "\" on " . $event_date . " has been cancelled.\r\n\nYours, MCR Event Booker";

		mail( $to, $subject, $body, ( "Content-type: text/plain; charset=ISO-8859-1; format=flowed\r\nContent-Transfer-Encoding: quoted-printable" ) );

		# Sleep for half a second to stop mails flooding out

		sleep( 0.5 );

		# Then remove guests from the booking list

		$dbh->query( "DELETE FROM " . $my_pre . "booking WHERE eventid=:eventid AND booker=:booker" );
		$dbh->bind( ":eventid", $_POST['delete_selection'] );
		$dbh->bind( ":booker", $crsid );
		$dbh->execute();

		# And remove the tickets from the tickets list
		$dbh->query( "DELETE FROM " . $my_pre . "booking_details WHERE eventid=:eventid AND booker=:booker" );
		$dbh->bind( ":eventid", $_POST['delete_selection'] );
		$dbh->bind( ":booker", $crsid );
		$dbh->execute();
	}

	# Now remove any queued applications

	$dbh->query( "SELECT DISTINCT booker FROM " . $my_pre . "queue WHERE eventid=:eventid" );
	$dbh->bind( ":eventid", $_POST['delete_selection'] );

	$result = $dbh->resultset();

	foreach ( $result as $booker ) {

		$crsid = $booker['booker'];

		echo "<p>Mailing Guest: " . $crsid . "@cam.ac.uk";

		$to      = $crsid . "@cam.ac.uk";
		$subject = "MCR Event Booker - event \"" . $event_name . "\" CANCELLED";
		$body    = "Dear MCR member,\r\n\n";
		$body    = $body . "It is with great regret that the MCR Committee notify you that event \"" . $event_name . "\" on " . $event_date . " has been cancelled.\r\n\nYours, MCR Event Booker";

		mail( $to, $subject, $body, ( "Content-type: text/plain; charset=ISO-8859-1; format=flowed\r\nContent-Transfer-Encoding: quoted-printable" ) );

		# Sleep for half a second to stop mails flooding out
		sleep( 0.5 );

		# Then remove guests from the queue list
		$dbh->query( "DELETE FROM " . $my_pre . "queue WHERE eventid=:eventid AND booker=:booker" );
		$dbh->bind( ":eventid", $_POST['delete_selection'] );
		$dbh->bind( ":booker", $crsid );
		$dbh->execute();

		# And remove the tickets from the queued tickets list
		$dbh->query( "DELETE FROM " . $my_pre . "queue_details WHERE eventid=:eventid AND booker=:booker" );
		$dbh->bind( ":eventid", $_POST['delete_selection'] );
		$dbh->bind( ":booker", $crsid );
		$dbh->execute();
	}

	echo "<p><b>FINISHED</b></p>";

	# Put a 'Return to admin button in'
	echo "<hr/>";
	echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
	echo "<input type=\"submit\" value=\"Return to Admin\">";
	echo "</form>";
}

function edit_user_form( classes\user &$user ) {

	# This function presents the user form for someone to add, remove and edit users.

	if ( $user->has_perm( 'e_adm' ) != true ) {
		if ( $user->has_perm( 's_adm' ) != true ) {
			trigger_error( $user->getValue( 'crsid' ) . " has inadequate permissions to perform user administration", E_USER_ERROR );
		}
	}

	echo "<h1>User Account Management</h1>\n";

	echo "<div class=\"note\">\n";
	echo "<p>To add users or edit their permissions, please enter a comma separated list of CRSids into the box, and select the type of access permission. List e.g. foo12,bar13,npx24,nlpd2</p>\n";
	echo "</div>\n";

	echo "<h2>Add Users to System</h2>\n";
	echo "<p>Add user(s) by listing their CRSid, and click 'Add User'</p>";
	echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";

	echo "<table border=\"0\" class=\"account_management\">";
	echo "<tr>";
	echo "<td colspan=\"3\"><input type=\"text\" name=\"users\" value=\"\"></td>";
	echo "<td>&nbsp;<input type=\"submit\" name=\"adduser\" value=\"Add User\"></td>";
	echo "</tr>";

	echo "<tr>";
	echo "<td>";
	# User permissions are now stored as a series of binary values in the access db.
	echo "<input type=\"hidden\" name=\"user_type[mcr_member]\" value=\"0\">";
	echo "<input type=\"checkbox\" name=\"user_type[mcr_member]\" value=\"1\" id=\"add_mcr\"><label for=\"add_mcr\">MCR Member</label><br/>";
	echo "<input type=\"hidden\" name=\"user_type[associate]\" value=\"0\">";
	echo "<input type=\"checkbox\" name=\"user_type[associate]\" value=\"1\" id=\"add_assoc\"><label for=\"add_assoc\">Clare Associate Member</label><br/>";
	echo "<input type=\"hidden\" name=\"user_type[cra]\" value=\"0\">";
	echo "<input type=\"checkbox\" name=\"user_type[cra]\" value=\"1\" id=\"add_cra\"><label for=\"add_cra\">CRA</label>";
	echo "</td>";

	echo "<td>";
	# Add permissions boxes (incl. default numbers)
	echo "<input type=\"hidden\" name=\"e_view\" value=\"0\">";
	echo "<input type=\"checkbox\" name=\"e_view\" value=\"1\" id=\"add_view\"><label for=\"add_view\">View</label><br/>";
	echo "<input type=\"hidden\" name=\"e_book\" value=\"0\">";
	echo "<input type=\"checkbox\" name=\"e_book\" value=\"1\" id=\"add_book\"><label for=\"add_book\">Book</label><br/>";
	echo "<input type=\"hidden\" name=\"e_adm\" value=\"0\">";
	echo "<input type=\"checkbox\" name=\"e_adm\" value=\"1\" id=\"add_admin\"><label for=\"add_admin\">Admin</label>";
	echo "<input type=\"hidden\" name=\"enabled\" value=\"0\">";
	echo "</td>";
	echo "<td>";
	echo "<input type=\"checkbox\" name=\"enabled\" value=\"1\" id=\"add_enabled\"><label for=\"add_enabled\">Enabled</label>";
	echo "</td>";
	echo "<td></td>";
	echo "</tr>";

	echo "</table>";
	echo "</form>";
	echo "<hr/>";

	# Edit users permissions
	echo "<h2>Edit User Permissions</h2>";
	echo "<p>Edit user(s) permission by listing their CRSid, setting their permissions, and click 'Edit User'</p>";
	echo "<form method=\"post\" action = \"" . $_SERVER['PHP_SELF'] . "\">";

	echo "<table border=\"0\" class=\"account_management\">";
	echo "<tr>";
	echo "<td colspan=\"3\"><input type=\"text\" name=\"users\" value=\"\"></td>";
	echo "<td>&nbsp;<input type=\"submit\" name=\"edituser\" value=\"Edit User\"></td>";
	echo "</tr>";

	echo "<tr>";
	echo "<td>";
	# User permissions are now stored as a series of binary values in the access db.
	echo "<input type=\"hidden\" name=\"user_type[mcr_member]\" value=\"0\">";
	echo "<input type=\"checkbox\" name=\"user_type[mcr_member]\" value=\"1\" id=\"edit_mcr\"><label for=\"edit_mcr\">MCR Member</label><br/>";
	echo "<input type=\"hidden\" name=\"user_type[associate]\" value=\"0\">";
	echo "<input type=\"checkbox\" name=\"user_type[associate]\" value=\"1\" id=\"edit_assoc\"><label for=\"edit_assoc\">Clare Associate Member</label><br/>";
	echo "<input type=\"hidden\" name=\"user_type[cra]\" value=\"0\">";
	echo "<input type=\"checkbox\" name=\"user_type[cra]\" value=\"1\" id=\"edit_cra\"><label for=\"edit_cra\">CRA</label>";
	echo "</td>";

	echo "<td>";
	# Add permissions boxes (incl. default numbers)
	echo "<input type=\"hidden\" name=\"e_view\" value=\"0\">";
	echo "<input type=\"checkbox\" name=\"e_view\" value=\"1\" id=\"edit_view\"><label for=\"edit_view\">View</label><br/>";
	echo "<input type=\"hidden\" name=\"e_book\" value=\"0\">";
	echo "<input type=\"checkbox\" name=\"e_book\" value=\"1\" id=\"edit_book\"><label for=\"edit_book\">Book</label><br/>";
	echo "<input type=\"hidden\" name=\"e_adm\" value=\"0\">";
	echo "<input type=\"checkbox\" name=\"e_adm\" value=\"1\" id=\"edit_admin\"><label for=\"edit_admin\">Admin</label><br/>";
	echo "</td><td>";
	echo "<input type=\"hidden\" name=\"enabled\" value=\"0\">";
	echo "<input type=\"checkbox\" name=\"enabled\" value=\"1\" id=\"edit_enabled\"><label for=\"edit_enabled\">Enabled</label>";
	echo "</td>";
	echo "<td></td>";
	echo "</tr>";
	echo "</table>";

	echo "</form>";
	echo "<hr/>";

	# This allows the removal of users from the system
	echo "<h2>Remove Users from System</h2>";
	echo "<p>Remove users(s) by listing their CRSid, and click 'Remove User'</p>";

	echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
	echo "<input type=\"text\" name=\"users\" value=\"\">";

	echo "&nbsp;<input type=\"submit\" name=\"removeuser\" value=\"Remove User\">";
	echo "</form>";
	echo "<HR>";

	# Put a 'Return to admin button in'
	echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
	echo "<input type=\"submit\" value=\"Return to Admin\">";
	echo "</form>";

}

function addusers() {

	# This is called to do the addition of users to the system
	# It expects a comma separated list of crsids.

	# Validation

	if ( ! preg_match( "/^[a-z0-9,]+$/", $_POST['users'] ) ) {
		trigger_error( "User list contains a non-alphanumeric and comma value, please correct", E_USER_ERROR );
	}

	if ( ! preg_match( "/^[0-1]$/", $_POST['e_view'] ) ) {
		trigger_error( "Access Permissions must be a choice of 0 or 1.", E_USER_ERROR );
	}

	if ( ! preg_match( "/^[0-1]$/", $_POST['e_book'] ) ) {
		trigger_error( "Access Permissions must be a choice of 0 or 1.", E_USER_ERROR );
	}

	if ( ! preg_match( "/^[0-1]$/", $_POST['e_adm'] ) ) {
		trigger_error( "Access Permissions must be a choice of 0 or 1.", E_USER_ERROR );
	}


	if ( ! preg_match( "/^[0-1]$/", $_POST['enabled'] ) ) {
		trigger_error( "Access Permissions must be a choice of 0 or 1.", E_USER_ERROR );
	}
	# Ok, so we have the list validated that we think we do. Split it up into an array of users:

	$userarray = explode( ',', $_POST['users'] );

	# Then for each user in the array, add them to the {$my_pre}access table

	foreach ( $userarray as $n_user ) {

		# Create a user object for the given user:
		$newuser = new classes\user();

		# Set their basic options
		$newuser->setValue( 'crsid', $n_user );
		$newuser->setValue( 's_adm', 1 );

		# Set type
		foreach ( $_POST['user_type'] as $type => $value ) {
			$newuser->setValue( $type, $value );
		}

		# Set enabled
		$newuser->setValue( 'enabled', $_POST['enabled'] );

		# Set permissions
		$newuser->setValue( 'e_view', $_POST['e_view'] );
		$newuser->setValue( 'e_book', $_POST['e_book'] );
		$newuser->setValue( 'e_adm', $_POST['e_adm'] );

		# Create the user
		$newuser->create_user();

	}

	# Back to Admin button

	echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
	echo "<input type=\"submit\" value=\"Return to Admin\">";
	echo "</form>";
}

function removeusers() {

	# This is called to do the removal of users from the system

	# Validation of input

	if ( ! preg_match( "/^[a-z0-9,]+$/", $_POST['users'] ) ) {
		trigger_error( "User list contains a non-alphanumeric and comma value, please correct", E_USER_ERROR );
	}

	# Ok, so we have the list validated that we think we do. Split it up into an array of users:

	$userarray = explode( ',', $_POST['users'] );

	# Then for each user in the array, remove them from the {$my_pre}access table

	foreach ( $userarray as $user ) {

		$target = new classes\user();

		# Only delete if the user exists
		if ( $target->getFromCRSID( $user ) ) {
			$target->delete();
		}
	}

	# Back to Admin
	echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
	echo "<input type=\"submit\" value=\"Return to Admin\">";
	echo "</form>";

}

function editusers() {

	# Validate input

	if ( ! preg_match( "/^[a-z0-9,]+$/", $_POST['users'] ) ) {
		trigger_error( "User list contains a non-alphanumeric and comma value, please correct", E_USER_ERROR );
	}

	if ( ! preg_match( "/^[0-1]$/", $_POST['e_view'] ) ) {
		trigger_error( "Illegal character used in permissions setting, please correct.", E_USER_ERROR );
	}

	if ( ! preg_match( "/^[0-1]$/", $_POST['e_book'] ) ) {
		trigger_error( "Illegal character used in permissions setting, please correct.", E_USER_ERROR );
	}

	if ( ! preg_match( "/^[0-1]$/", $_POST['e_adm'] ) ) {
		trigger_error( "Illegal character used in permissions setting, please correct.", E_USER_ERROR );
	}

	# Break up the list of users
	$userarray = explode( ',', $_POST['users'] );

	foreach ( $userarray as $user ) {

		$target = new classes\user();
		if ( $target->getFromCRSID( $user ) ) {
			# Set values
			$target->setValue( 'e_view', $_POST['e_view'] );
			$target->setValue( 'e_book', $_POST['e_book'] );
			$target->setValue( 'e_adm', $_POST['e_adm'] );

			foreach ( $_POST['user_type'] as $type => $value ) {
				$target->setValue( $type, $value );
			}

			$target->setValue( 'enabled', $_POST['enabled'] );

			# Commit the user changes
			$target->commit_events_user();
			echo "<p>User " . $target->getValue( 'crsid' ) . " CHANGED.</p>\n";
		}
	}
}

function editDefaultForm() {
	# Prints out the editDefault form for users to set defaults.

	global $dbh;
	global $my_pre;

	# Get the default values

	$dbh->query( "SELECT * FROM " . $my_pre . "defaults" );

	$result = $dbh->resultset();

	echo "<h1>Edit Default Values</h1>\n";
	echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
	echo "<table>\n";
	echo "<tr><th>Setting</th><th>Value</th></tr>\n";

	$i = 0;

	foreach ( $result as $variablearray ) {
		echo "<tr>";
		echo "<td>" . $variablearray['name'] . "</td>";
		echo "<td><input type=\"hidden\" name=\"variablearray[" . $i . "][id]\" value=\"" . $variablearray['id'] . "\">";
		echo "<input type=\"text\" name=\"variablearray[" . $i . "][value]\" value=\"" . $variablearray['value'] . "\"></td>";
		echo "</tr>";
		$i ++;
	}

	echo "</table>\n";
	echo "<hr/>";
	echo "<input type=\"submit\" name=\"editDefault\" value=\"Submit\">";
	echo "</form>";
}

function editDefault() {
	# Runs the editDefault alteration

	global $dbh;
	global $my_pre;

	# Validate the given inputs

	foreach ( $_POST['variablearray'] as $var ) {
		validate_is_number( $var['value'], $var['id'] );
	}

	# Create the PDO to put the info into the database

	foreach ( $_POST['variablearray'] as $var ) {

		$dbh->query( "UPDATE " . $my_pre . "defaults SET value=:value where id=:id" );

		$dbh->bind( ":value", $var['value'] );
		$dbh->bind( ":id", $var['id'] );

		$dbh->execute();
	}

	# Confirm to the user what we've done

	echo "<p>Successfully updated default values for the events booking system.</p>";

}

function editEventForm() {

	global $dbh;
	global $my_pre;

	# This form allows the editing of an event's information
	# Select event
	$dbh->query( "SELECT * FROM " . $my_pre . "eventslist WHERE NOW() < event_date ORDER BY event_date" );
	$result = $dbh->resultset();

	echo "<h1>Edit Events</h1>\n";

	if ( count( $result ) > 0 ) {
		echo "<p>Please select an event to edit.</p>";
		echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
		foreach ( $result as $event ) {
			echo "<h3 class=\"event_name\"><input type=\"radio\" name=\"eventid\" id=\"event_" . $event['id'] . "\" value=\"" . $event['id'] . "\"> <label for=\"event_" . $event['id'] . "\">" . $event['name'] . "</label><span class=\"date\">" . date( 'd/m/Y', strtotime( $event['event_date'] ) ) . "</span></h3>\n";
			echo "<table border=\"0\" cellspacing=\"0\">";
			echo "<tr><td>Booking Opens:</td><td>" . date( 'd/m/Y - H:i', strtotime( $event['open_date'] ) ) . "</td></tr>";
			echo "<tr><td>Booking Closes:</td><td>" . date( 'd/m/Y - H:i', strtotime( $event['close_date'] ) ) . "</td></tr>";
			echo "<tr><td>Event Date:</td><td>" . date( 'd/m/Y - H:i', strtotime( $event['event_date'] ) ) . "</td></tr>";
			echo "</table>";
		}
		echo "<hr/>";
		echo "<input type=\"Submit\" name=\"edit_event\" value=\"Edit Selected Event\"> ";
		echo "<input type=\"submit\" value=\"Return to Admin\">";
		echo "</form>";
	} else {
		echo "<p>There are currently no events which are yet to happen.</p>";
		# Return to admin
		echo "<hr/>";
		echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
		echo "<input type=\"submit\" value=\"Return to Admin\">";
		echo "</form>";
	}
}

function editEvent( $eventid ) {
	# Edits an existing event.

	if ( ! preg_match( "/^[0-9]+$/", $eventid ) ) {
		trigger_error( "Delete Selection is not a number, please correct", E_USER_ERROR );
	}

	# Get the existing values from the database

	$event = new classes\event();
	$event->getEventFromID( $eventid );


	# Set those which are stored in the defaults table

	$def_main_price   = $event->getValue( 'cost_normal' );
	$def_guest_price  = $event->getValue( 'cost_second' );
	$def_max_guests   = $event->getValue( 'max_guests' );
	$def_total_guests = $event->getValue( 'total_guests' );
	$name             = $event->getValue( 'name' );
	$event_date       = new Datetime( $event->getValue( 'event_date' ) );
	$open_date        = new Datetime( $event->getValue( 'open_date' ) );
	$close_date       = new Datetime( $event->getValue( 'close_date' ) );
	$mcr_member       = $event->getValue( 'mcr_member' );
	$cra              = $event->getValue( 'cra' );
	$associate_member = $event->getValue( 'associate_member' );
	?>
    <h1>Edit Event</h1>
    <h3>Event Details</h3>
    <form method="post" action=" <?php echo $_SERVER['PHP_SELF']; ?> ">

        <dl>
            <dt>Event name</dt>
            <dd><input type="text" name="event_name" required value="<?php echo $name; ?>" placeholder="e.g. MCR Formal"
                       title="Please enter event name. The event date will automatically be added." size="30"
                       maxlength="50"></dd>
            <dt><label for="total_guests">Total number of available tickets</label></dt>
            <dd><input type="number" min="0" size="5" id="total_guests" name="total_guests"
                       value="<?php echo $def_total_guests; ?>" required></dd>
            <dt><label for="max_guests">Maximum guests each person can bring</label></dt>
            <dd><input type="number" min="0" size="5" id="max_guests" name="max_guests"
                       value="<?php echo $def_max_guests; ?>" required></dd>


            <dt>Enter the date for the event</dt>
            <dd>
                <table border="0" class="datetime">
                    <tr>
                        <td><input type="date" title="event date" name="event[date]"
                                   min="<?php echo date( 'Y-m-d' ); ?>"
                                   value="<?php echo $event_date->format( 'Y-m-d' ); ?>"/></td>
                        <td><input type="time" title="event time" name="event[time]" min="00:00"
                                   value="<?php echo $event_date->format( 'H:i' ); ?>" step="900" required/></td>
                    </tr>
                </table>
            </dd>

            <dt>Enter the opening date for the event</dt>
            <dd>
                <table border="0" class="datetime">
                    <tr>
                        <td><input type="date" title="open date" name="open[date]"
                                   value="<?php echo $open_date->format( 'Y-m-d' ); ?>"/></td>
                        <td><input type="time" title="open time" name="open[time]" min="00:00"
                                   value="<?php echo $open_date->format( 'H:i' ); ?>" step="900" required/></td>
                    </tr>
                </table>
            </dd>

            <dt>Enter the closing date for the event</dt>
            <dd>
                <table border="0" class="datetime">
                    <tr>
                        <td><input type="date" title="close date" name="close[date]"
                                   min="<?php echo date( 'Y-m-d' ); ?>"
                                   value="<?php echo $close_date->format( 'Y-m-d' ); ?>"/></td>
                        <td><input type="time" title="close time" name="close[time]" min="00:00"
                                   value="<?php echo $close_date->format( 'H:i' ); ?>" step="900" required/></td>
                    </tr>
                </table>
            </dd>
        </dl>

        <h3>Pricing</h3>
        <dl>
            <dt><label for="standard_price">Price for a standard ticket</label></dt>
            <dd>&pound; <input type="text" id="standard_price" name="standard_price" size="5"
                               value="<?php echo $def_main_price; ?>" required pattern="\d+\.\d\d"
                               title="Enter price in pounds and pence, e.g. 7.50"></dd>
            <dt><label for="second_price">Price for first guest ticket</label></dt>
            <dd>&pound; <input type="text" id="second_price" name="second_price" size="5"
                               value="<?php echo $def_guest_price; ?>" required pattern="\d+\.\d\d"
                               title="Enter price in pounds and pence, e.g. 7.50"></dd>
        </dl>

        <h3>Guest Type</h3>
        <input type="hidden" name="guest_type[mcr_member]" value="0">
        <input type="checkbox" id="mcr" name="guest_type[mcr_member]" value="1" <?php if ( $mcr_member ) {
			echo "checked";
		} ?>><label for="mcr">MCR Members</label>
        <input type="hidden" name="guest_type[associate_member]" value="0">
        <input type="checkbox" name="guest_type[associate_member]" id="associate"
               value="1" <?php if ( $associate_member ) {
			echo "checked";
		} ?>><label for="associate">Associate Members</label>
        <input type="hidden" name="guest_type[cra]" value="0">
        <input type="checkbox" id="cra" name="guest_type[cra]" value="1" <?php if ( $cra ) {
			echo "checked";
		} ?>><label for="cra">CRAs</label>
        <input type="hidden" name="eventid" value="<?php echo $eventid; ?>">
        <hr/>

        <table border="0" cellspacing="0">
            <tr>
                <td><input type="submit" name="commit_edit_event" value="Edit Event"></td>
                <td><input type="submit" value="Return to Admin"></td>
            </tr>
        </table>
    </form>
	<?php
}

function CommiteditEvent( $eventid ) {
	# Edits an existing event.
	global $logger;
	if ( ! preg_match( "/^[0-9]+$/", $eventid ) ) {
		$logger->error( "Delete Selection is not a number, please correct" );
		trigger_error( "Delete Selection is not a number, please correct", E_USER_ERROR );
	}

	$event = new classes\event();
	$event->getEventFromID( $eventid );

	# Validate and set the new values
	$eventday = $_POST['event'];
	$openday  = $_POST['open'];
	$closeday = $_POST['close'];

	$event_date = new Datetime( $eventday['date'] . " " . $eventday['time'] );
	$open_date  = new Datetime( $openday['date'] . " " . $openday['time'] );
	$close_date = new Datetime( $closeday['date'] . " " . $closeday['time'] );

	$event->setValue( 'name', $_POST['event_name'] );

	$event->setValue( 'cost_normal', $_POST['standard_price'] );
	$event->setValue( 'cost_second', $_POST['second_price'] );
	$event->setValue( 'max_guests', $_POST['max_guests'] );
	$event->setValue( 'total_guests', $_POST['total_guests'] );

	$event->setValue( 'event_date', $event_date->format( 'Y-m-d H:i:s' ) );
	$event->setValue( 'open_date', $open_date->format( 'Y-m-d H:i:s' ) );
	$event->setValue( 'close_date', $close_date->format( 'Y-m-d H:i:s' ) );

	foreach ( $_POST['guest_type'] as $guesttype => $value ) {
		$event->setValue( $guesttype, $value );
	}
	# Commit the changed event to the database
	$logger->debug( "Committing event" );
	$event->commit();
	$logger->debug( "event committed" );

	$command = escapeshellcmd( "./updatequeue.py $eventid" );
	$output  = shell_exec( $command );
	echo $output;
	# Let the user know what we've changed.
	echo "<p>Congratulations, you have successfully edited the following event:</p>";
	echo "<p><b>Event Name: </b>" . $event->getValue( 'name' ) . "</p>";
	echo "<p><b>On the Date: </b>" . $event->getValue( 'event_date' ) . "</p>";
	echo "<p><b>Total Guests: </b>" . $event->getValue( 'total_guests' ) . "</p>";
	echo "<p><b>Maximum Guests for each Member: </b>" . $event->getValue( 'max_guests' ) . "</p>";
	echo "<p><b>A standard ticket will cost:</b> &#163;" . $event->getValue( 'cost_normal' ) . "</p>";
	echo "<p><b>The second ticket will cost:</b> &#163;" . $event->getValue( 'cost_second' ) . "</p>";
	echo "<p><b>Booking will open on: </b>" . $event->getValue( 'open_date' ) . "</p>";
	echo "<p><b>Booking will close on: </b>" . $event->getValue( 'close_date' ) . "</p>";
	echo "<p>Yours, The MCR Booking system</p>";

	echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\">";
	echo "<input type=\"submit\" value=\"Return to Admin\">";
	echo "</form>";


}

?>