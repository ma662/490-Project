<?php
include_once 'class.ConvertForAPI.php';

//for error logging
require_once('../../../git/rabbitmqphp_example/path.inc');
require_once('../../../git/rabbitmqphp_example/get_host_info.inc');
require_once('../../../git/rabbitmqphp_example/rabbitMQLib.inc');

$client = new rabbitMQClient("../toLog.ini","testServer");

$request = array();

class movieAPIRecommendations {

	public static function _movieRecommend($parameters, $pagenum = 1) {
	
		$title = $parameters[0];
		if(count($parameters) > 1)
		  $year = $parameters[1];
		else
		 $year = '';		

		$imdbid = ConvertForAPI::_movieRedirect($title, $year);
		$tmdbid = ConvertForAPI::_movieIMDBtoTMDB($imdbid);

		//echo $tmdbid;
		
		$page = "page=$pagenum";		
		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => "https://api.themoviedb.org/3/movie/$tmdbid/recommendations?$page&language=en-US&api_key=78d3b2e412d269add2b072f074d49fa3",
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "GET",
		  CURLOPT_POSTFIELDS => "{}",
		));

		$jsonResponse = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err)
		{
			echo "cURL Error #:" . $err;
			$error = (date('m/d/Y h:i:s a', time())." ".gethostname()." "." Error occured in ".__FILE__." LINE ".__LINE__." cURL Error #: ".$err.PHP_EOL);
			
			$request['type'] = "error";
			$request['data'] = $error;

			$client->send_request($request);
			return $err;
		}
		else
		{
			//echo $jsonResponse;

			$parts = explode("}],", $jsonResponse);
			$parts = explode(":[{", $parts[0]);	
			$parts = explode("},{", $parts[1]);
	
			//print_r($parts);
			for($i = 0; $i < count($parts); $i++)
			{
				$parts[$i] = trim($parts[$i], "{}]\\");
				$masterArray[$i] = explode(",\"", $parts[$i]);
		
				for($j = 0; $j < count($masterArray[$i]); $j++)
				{
					$chunk = explode("\":",$masterArray[$i][$j]);
					$chunk[0] = trim($chunk[0], "\"");
					$chunk[1] = trim($chunk[1], "\"");
					$arrayResponse[$i][$chunk[0]] = $chunk[1];
				}
			}
			//print_r($arrayResponse);
			for($i = 0; $i < count($arrayResponse); $i++)
			{
				$arrayResponse[$i]["poster_path"] = trim($arrayResponse[$i]["poster_path"], "\\");
				$arrayResponse[$i]["backdrop_path"] = trim($arrayResponse[$i]["backdrop_path"], "\\");
		
				$arrayResponse[$i]["genre_ids"] = trim($arrayResponse[$i]["genre_ids"], "[]");
				$arrayResponse[$i]["genre_ids"] = explode(",", $arrayResponse[$i]["genre_ids"]);

				for($j = 0; $j < count($arrayResponse[$i]["genre_ids"]); $j++)
				{
					$arrayResponse[$i]["genre_ids"][$j] = ConvertForAPI::_genreConvertToString($arrayResponse[$i]["genre_ids"][$j]);
				}
			}
			//print_r($arrayResponse);
			return $arrayResponse;
		}
	}
}
?>