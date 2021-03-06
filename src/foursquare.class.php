<?php
	/* class OAuthFoursquare
	 * /src/foursquare.class.php
	 */
	require_once 'oauth.class.php';
	
	class OAuthFoursquare extends OAuth2 {
		// Options. These shouldn't be modified here, but using the OAuth::options() function.
		public $options = Array(
			"session_prefix"		=> "foursquare_",
			"dialog"				=> Array("base_url" => "https://foursquare.com/oauth2/authorize", "scope_separator" => " "),
			"api"					=> Array("base_url" => "https://api.foursquare.com/v2", "token_auth" => false, "headers" => Array("User-Agent" => "OAuth 2.0 Client https://github.com/samuelthomas2774/oauth-client"), "callback" => null),
			"requests"				=> Array("/oauth/token" => "https://foursquare.com/oauth2/access_token", "/oauth/token:response" => "json", "/oauth/token/debug" => "https://foursquare.com/oauth2/access_token"),
			"errors"				=> Array("throw" => true)
		);
		
		// function api(). Makes a new request to the server's API.
		public function api($method, $url, $params = Array(), $headers = Array(), $auth = false) {
			if(!isset($params["oauth_token"])) $params["oauth_token"] = $this->accessToken();
			if(!isset($params["v"])) $params["v"] = "20140806";
			if(!isset($params["m"])) $params["m"] = "foursquare";
			return parent::api($method, $url, $params, $headers, $auth);
		}
		
		// function userProfile(). Fetches the current user's profile.
		public function userProfile() {
			$request = $this->api("GET", "/users/self");
			
			$request->execute();
			return $request->responseObject()->response->user;
		}
	}
	
