<?php

require_once(__DIR__ . '/wasCreds.php');

class wasCourseScraper
{
	/**
	 * wasCourseScraper constructor.
	 */

	const VERBOSE = true;
	const REQUEST_TYPES = ['GET', 'POST'];

	function __construct()
	{
		$cookieFile = __DIR__ . "/cookieJar";
		file_put_contents($cookieFile, "");
		$this->ch = $this->initCurl($cookieFile);
	}

	public function run()
	{
		$this->logIn(wasCreds::WEBADVISOR_USERNAME, wasCreds::WEBADVISOR_PASS);
	}

	/**
	 * Logs into UoG WebAdvisor
	 *
	 * @param string $user Central Login username.
	 * @param string $pass Central Login password.
	 *
	 * @throws Exception
	 */
	private function logIn($user, $pass)
	{
		$response = $this->request("GET", "https://webadvisor.uoguelph.ca");
		preg_match("/\'(https.*)\'/", $response, $matches);

		if(empty($matches)) {
			throw new Exception('Error: No redirect while logging in.');
		}

		$url = "https://webadvisor.uoguelph.ca/WebAdvisor/WebAdvisor";
		$query = [
			"TYPE" => "M",
			"PID"  => "CORE-WBMAIN"
		];
		$this->request("GET", $url, $query);

		$query["TOKENIDX"] = "";
		$response = $this->request("GET", $url, $query);
		preg_match("/Set-Cookie: LASTTOKEN=(.*)/", $response, $matches);

		$token = trim($matches[1]);
		$query['TOKENIDX'] = $token;
		$this->request("GET", $url, $query);

		$returnUrl = $this->formatQuery($url, $query);

		$query = [
			"type"     => "P",
			"pid"      => "UT-LGRQ",
			"TOKENIDX" => $token
		];
		$response = $this->request("GET", $url, $query);

		preg_match("/Location: (https.*)/i", $response, $matches);
		$this->request("GET", $matches[1]);

		$postData = [
			"USER.NAME"  => $user,
			"CURR.PWD"   => $pass,
			"RETURN.URL" => $returnUrl
		];
		$query = [
			"TOKENIDX"       => $token,
			"SS"             => "LGRQ",
			"URL"            => $returnUrl,
		];
		$response = $this->request("POST", $url, $query, $postData);

		preg_match("/Location: (https.*)/i", $response, $matches);
		$response = $this->request("GET", $matches[1]);
	}

	/**
	 * Perform a GET request against a URL.
	 *
	 * @param string $type        The type of requests (GET, POST, etc.)
	 * @param string $url         The URL to request against.
	 * @param array  $queryParams Associative array of key-value params (URL query).
	 * @param array  $postData    Associative array of key-value params (post data).
	 *
	 * @return string The server's response.
	 * @throws Exception
	 */
	private function request($type, $url, $queryParams = null, $postData = null)
	{
		if(!in_array($type, self::REQUEST_TYPES)) {
			throw new Exception("Error: Unexpected request type: $type.");
		}

		$formattedUrl = $this->formatQuery($url, $queryParams);
		$ch = $this->ch;

		if(self::VERBOSE) {
			echo "[Info]: $type $formattedUrl\n";
		}

		$payload = "";
		if(!empty($postData)) {
			$payload = http_build_query($postData);
		}

		curl_setopt_array($ch, [
			CURLOPT_URL           => $formattedUrl,
			CURLOPT_CUSTOMREQUEST => $type,
			CURLOPT_POSTFIELDS    => $payload
		]);
		$result = curl_exec($ch);

		if(!$result) {
			throw new Exception("Curl error: " . curl_error($ch));
		}

		return $result;
	}

	/**
	 * Convert dictionary of array parameters and append to URL.
	 *
	 * @param string $url         The base URL.
	 * @param array  $queryParams Key-value query parameters.
	 *
	 * @return string www.example.com/key1=value2
	 * @throws Exception On empty URL.
	 */
	private function formatQuery($url, $queryParams)
	{
		$url = trim($url);

		if(empty($url)) {
			throw new Exception("Error: invalid URL: |$url|");
		}

		if(empty($queryParams)) {
			return $url;
		}

		return $url . "?" . http_build_query($queryParams);
	}

	/**
	 * Create and initialize a PHP Curl instance
	 *
	 * @param string $cookieJar Location (filepath) to store and pull cookies.
	 *
	 * @return resource
	 */
	private function initCurl($cookieJar)
	{
		$curl = curl_init();

		curl_setopt_array($curl, [
			CURLOPT_COOKIEJAR      => $cookieJar,
			CURLOPT_COOKIEFILE     => $cookieJar,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => "",
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.52 Safari/537.17',
			CURLOPT_VERBOSE        => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_HEADER         => true
		]);

		return $curl;
	}
}

$wasCS = new wasCourseScraper();
$wasCS->run();
