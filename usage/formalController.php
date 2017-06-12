<?php

/**
 * Created by PhpStorm.
 * User: rjgun
 * Date: 10/04/2016
 * Time: 19:24
 */
use Jacwright\RestServer\RestException;

require 'database.class.php';
require 'config.php';

function test_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

class puntsController
{
    private $db;

    /**
     * Gets the Formal Usage
     *
     * @url GET /
     * @return
     */
    public function getFormal()
    {
        $this->db = new Database();
        $this->db->query('SELECT
a.id,
a.name,
a.event_date,
a.cost_normal,
a.cost_second,
a.current_guests/a.total_guests AS Sold_Ratio,
a.total_guests,
a.current_guests,
b.mcr,
b.guest

FROM mcrevents_eventslist AS a INNER JOIN (SELECT eventid, SUM(type) AS mcr, COUNT(type)-SUM(type) AS guest FROM mcrevents_booking_details WHERE admin=0 GROUP BY eventid ) AS b
ON a.id = b.eventid
WHERE
DATE_FORMAT(a.event_date,\'%a\')=\'fri\'
AND a.total_guests >= 70

ORDER BY a.event_date  DESC');
        $row = $this->db->resultset();
        return $row;
    }

    /**
     * Throws an error
     *
     * @url GET /error
     */
    public function throwError()
    {
        throw new RestException(401, "Empty password not allowed");
    }
}
