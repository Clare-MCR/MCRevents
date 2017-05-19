<?php
/**
 * Created by PhpStorm.
 * User: rg12
 * Date: 19/05/2017
 * Time: 14:39
 */

namespace claremcr\clareevents\classes;


class email extends genericitem {
	protected $from;
	protected $to;
	protected $subject;
	protected $body;
	protected $csv;
	protected $csvName;
	protected $logger;

	function __construct() {
		parent::__construct();
		global $logger;
		$this->logger = &$logger;
		$this->logger->info( "setting defaults email" );
		// Set Defaults
		$this->from    = "mcr-socsec@clare.cam.ac.uk";
		$this->to      = "mcr-computing@clare.cam.ac.uk";
		$this->subject = "MCREvents";
		$this->body    = "... what ever you want to appear in the body";
		$this->csv     = "";
		$this->csvName = "mycsv.csv";
	}

	function send() {

		$random_hash = md5( date( 'r', time() ) );
		$headers     = "From: $this->from\r\nReply-To: $this->from";
		$headers    .= "\r\nMIME_Version: 1.0\r\n";
		if ( $this->csv != "" ) {
			$attachment = chunk_split( base64_encode( $this->csv ) );
			$headers    .= "Content-Type: multipart/mixed; boundary=\"PHP-mixed-{$random_hash}\"\r\n\r\n";
			$headers .= "This is a multi-part message in MIME format.\r\n";
			$output = "
--PHP-mixed-$random_hash
Content-Type: multipart/alternative; boundary='PHP-alt-$random_hash'
--PHP-alt-$random_hash
Content-Type: text/plain; charset='iso-8859-1'
Content-Transfer-Encoding: 7bit

$this->body

--PHP-mixed-$random_hash
Content-Type: text/csv; name=mycsv.csv
Content-Transfer-Encoding: base64
Content-Disposition: attachment

$attachment
--PHP-mixed-$random_hash--";
		} else {
			$headers    .= "\r\nContent-Type: text/plain; charset=ISO-8859-1; format=flowed\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n";
			$output=$this->body;
		}
		$this->logger->debug("Sending Email to $this->to");
		$this->logger->debug("Sending Email from $this->from");
		$this->logger->debug("Email body: $this->body");
		$this->logger->debug("Email subject: $this->subject");
		$this->logger->debug("csv: $this->csv");

		mail( $this->to, $this->subject, $output, $headers );
	}

}