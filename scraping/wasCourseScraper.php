<?php

require_once(__DIR__ . '/wasCreds.php');

class wasCourseScraper
{
	/**
	 * wasCourseScraper constructor.
	 */

	const VERBOSE = true;

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
		$response = $this->get("https://webadvisor.uoguelph.ca");
		preg_match("/\'(https.*)\'/", $response, $matches);

		if(empty($matches)) {
			throw new Exception('Error: No redirect while logging in.');
		}

		$url = "https://webadvisor.uoguelph.ca/WebAdvisor/WebAdvisor";
		$query = [
			"TYPE" => "M",
			"PID"  => "CORE-WBMAIN"];
		$this->get($url, $query);

		$query["TOKENIDX"] = "";
		$response = $this->get($url, $query);
		preg_match("/Set-Cookie: LASTTOKEN=(.*)/", $response, $matches);

		$token = $matches[1];
		$query['TOKENIDX'] = $token;
		$this->get($url, $query);

		$query = [
			"type"     => "P",
			"pid"      => "UT-LGRQ",
			"TOKENIDX" => $token];
		$this->get($url, $query);

		$returnUrl = $this->formatQuery($url, $query);
		$post_fields = [
			"USER.NAME"  => $user,
			"CURR.PWD"   => $pass,
			"RETURN.URL" => $returnUrl];
		$query = [
			"TOKENIDX"       => $token,
			"SS"             => "LGRQ",
			"URL"            => $returnUrl,
			"SUBMIT_OPTIONS" => ""];
	}

	/**
	 * Perform a GET request against a URL.
	 *
	 * @param string $url         The URl to request against.
	 * @param array  $queryParams Associative array of key-value params.
	 *
	 * @return string GET request response.
	 * @throws Exception
	 */
	private function get($url, $queryParams = null)
	{
		$formattedUrl = $this->formatQuery($url, $queryParams);
		$ch = $this->ch;

		if(self::VERBOSE) {
			echo "[Info]: GET $formattedUrl\n";
		}

		curl_setopt_array($ch, [
			CURLOPT_URL           => $formattedUrl,
			CURLOPT_CUSTOMREQUEST => "GET",
		]);
		$result = curl_exec($ch);

		if(!$result) {
			throw new Exception("Curl error: " . curl_error($ch));
		}

		return $result;
	}

	private function post($url, $queryParams = null, $postData = null)
	{

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
		if(empty($url)) {
			throw new Exception("Error: invalid URL: |$url|");
		}

		if(empty($queryParams)) {
			return $url;
		}

//		if(substr($url, -strlen($url)) !== "/") {
//			$url .= "/";
//		}

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

		$a = true;

		curl_setopt_array($curl, [
			CURLOPT_COOKIEJAR      => $cookieJar,
			CURLOPT_COOKIEFILE     => $cookieJar,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => "",
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.52 Safari/537.17',
			CURLOPT_VERBOSE        => false,
			//			CURLOPT_VERBOSE        => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HEADER         => true
		]);

		return $curl;
	}
}

$wasCS = new wasCourseScraper();
$wasCS->run();