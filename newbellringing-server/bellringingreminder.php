
<?php // bellringingreminder.php

require_once 'inc/db_connect.php';

define("lb", "<br/>");

// cron job run every day at 6:00 AM to send email to those scheduled for that day
$dt = new DateTime();
$dt1 = $dt->format('Y-m-d');
$dt2 = $dt->format('l, F j, Y'); // Formatted as Wednesday, February 11, 2015

$arrayOfFullDailySchedules = getDailySchedules($dbh, $dt1);

try
{
    $sql = "select * from administrators where fname is not null order by listing_order";
    $admins = $dbh->query($sql);

    $sql = "select distinct ringer from v_schedule where dt = ? order by ringer";
    $query = $dbh->prepare($sql);
    $query->execute(array($dt1));
    $dailyRingerIds = $query->fetchAll();

    $sql = "select * from v_schedule where dt = ? and ringer = ? order by slot";
    $query2 = $dbh->prepare($sql);
    foreach ($dailyRingerIds as $dr) {
        $query2->execute(array($dt1, $dr["ringer"]));
        $schedules = $query2->fetchAll();

        sendEmails($schedules, $dt2, $arrayOfFullDailySchedules, $admins);
    }

    echo "Bell Ringing Reminder has run successfully on " . $dt2 . "\n";
} catch (Exception $ex) {
    echo "Error Ocurred: " . $ex->getMessage();
    return;
}

function sendEmails($schedules, $dt2, $arrayOfFullDailySchedules, $admins) {
    $msg = "<div style='margin-left: 20%;'>";
    $firstTime = true;
    $i = 1;
    foreach ($schedules as $sch) {
        if ($firstTime) {
            $msg .= sprintf("<h3>Hi %s,<br/><br/>\r\n\r\nOn %s you are scheduled for bell ringing as follows:</h3><ol>\r\n", $sch["fname"], $dt2);
            $firstTime = false;
        }
        $msg .= sprintf("<li><h4>%s at %s</li></h4>\r\n", $sch['location_name'], $sch['interval']);

    }
    $msg .= "</ol>";

    $msg .= "<h3 style='color:red;'>If you are unable to attend your scheduled assignment please find a substitute and<br/>";
    $msg .= "inform the manger of this substitution so that proper credit may be given to your substitute.</h3>";
    $msg .= "<h2 style='color:darkred;'>Thank you for your participation in this important function.</h2>.";
    $msg .= "<h3>Below are today's schedules for all the bellringing sites.<br/>";
    $msg .= "You may use these to see who you will be relieving and who will relieve you.</h3>";

    $msg .= "<table style='border-collapse: collapse;'>";
    $msg .= "<tr><td colspan='5'><b>Here are contacts if you need assistance</b></td></tr>";
    foreach ($admins as $admin) {
        $msg .= "<tr>";
        $msg .= sprintf("<td style='border: 1px solid black;padding: 5px;'>%s</td>", $admin['title']);
        $msg .= sprintf("<td style='border: 1px solid black;padding: 5px;'>%s</td>", $admin['fname']);
        $msg .= sprintf("<td style='border: 1px solid black;padding: 5px;'>%s</td>", $admin['lname']);
        $msg .= sprintf("<td style='border: 1px solid black;padding: 5px;'>%s</td>", $admin['phone']);
        $msg .= sprintf("<td style='border: 1px solid black;padding: 5px;'>%s</td>", $admin['email']);
        $msg .= "</tr>";
    }
    $msg .= "</table>";

    $msg .= "<br/><br/><i>This is an unmonitored mailbox. Please do not reply to this message.</i><br/><br/>";
    foreach ($arrayOfFullDailySchedules as $schedule) {
        $msg .= $schedule . lb;
    }
    $msg .= "</div>";

    try {
        $to = $sch["email"];
        echo $to . "\n";
        $subject = sprintf("Bell ringing schedule for %s", $dt2);
        $header = "From:noreply-bellringing@monumenthillkiwanis.org \r\n";
        $header .= "MIME-Version: 1.0\r\n";
        $header .= "Content-type: text/html\r\n";
        $retval = mail($to, $subject, $msg, $header);
    } catch (exception $ex) {
        echo $ex->getMessage();
    }
}

function getDailySchedules($dbh, $dt) {

    try {
        $sql = "select id from locations";
        $query = $dbh->prepare($sql);
        $query->execute();
        while ($location_id = $query->fetch(PDO::FETCH_BOTH)) {

            $arrayOfFullDailySchedules[$location_id['id']] = slotsForDayAndLocation($dbh, $dt, $location_id['id']);

        }
        return $arrayOfFullDailySchedules;
    } catch (exception $ex) {
        echo $ex->getMessage();
    }
}

function slotsForDayAndLocation($dbh, $ringDate, $location) {
    try {
        $sql = "select id, time from timeslots order by id";
        $query = $dbh->prepare($sql);
        $query->execute();
        $arrayTimeslots = [];
        while ($timeslots = $query->fetch(PDO::FETCH_BOTH)) {
            array_push($arrayTimeslots, $timeslots);
        }

        $sql = "select id, location_name from locations order by id";
        $query = $dbh->prepare($sql);
        $query->execute();
        $arrayLocations = [];
        while ($locations = $query->fetch(PDO::FETCH_BOTH)) {
            array_push($arrayLocations, $locations);
        }

        $loc_name = '';
        foreach ($arrayLocations as $loc) {
            if ($loc['id'] == (int) $location) {
                $loc_name = $loc['location_name'];
            }

        }

        $sql = "SELECT * FROM v_schedule_with_phone where dt = ? and location = ? and slot=?";
        $query = $dbh->prepare($sql);

        return showAllSlots($query, $ringDate, $location, $arrayTimeslots, $loc_name);

    } catch (exception $ex) {
        echo $ex->getMessage();
    }
}

function showAllSlots($query, $ringDate, $location, $arrayTimeslots, $loc_name) {

    $rd = new DateTime($ringDate);
    try {
        $returnString = "";

        $returnString .= "<table style='border-collapse: collapse;'><tr>";
        $returnString .= "<td colspan='4'><b>Time slots for <b>" . $loc_name . '</b> on <b>' . $rd->format('l, F j, Y') . "</b></td></b>";
        $rowString = "";
        foreach ($arrayTimeslots as $timeslot) {

            $theArray = array($ringDate, (int) $location, $timeslot['id']);

            $query->execute($theArray);

            $slotFromView = $query->fetch(PDO::FETCH_ASSOC);

            if ($query->rowCount() != 0) {

                $ringerName = sprintf("<td style='border: 1px solid black;padding: 5px;'>%s</td><td style='border: 1px solid black;padding: 5px;'>%s</td><td style='border: 1px solid black;padding: 5px;'>%s</td>", $timeslot['time'],
                    $slotFromView['fname'] . " " . $slotFromView['lname'], $slotFromView['phone']);
            } else {
                $ringerName = sprintf("<td style='border: 1px solid black;padding: 5px;'>%s</td><td style='border: 1px solid black;padding: 5px;'>%s</td><td style='border: 1px solid black;padding: 5px;'>%s</td>",
                    $timeslot['time'], "<span style='color:red;'>Empty</span>", "");
            }

            $rowString .= "<tr>";
            $rowString .= $ringerName;
            $rowString .= "</tr>";

        }

        $returnString .= $rowString . "</table>" . lb . lb;

        return $returnString;

    } catch (exception $ex) {
        echo $ex->getMessage();
    }
}

?>