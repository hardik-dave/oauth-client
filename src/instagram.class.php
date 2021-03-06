<?php
	/* class OAuthInstagram
	 * /src/instagram.class.php
	 */
	require_once 'oauth.class.php';
	
	class OAuthInstagram extends OAuth2 {
		// Options. These shouldn't be modified here, but using the OAuth2::options() function.
		public $options = Array(
			"session_prefix"		=> "instagram_",
			"dialog"				=> Array("base_url" => "https://api.instagram.com/oauth/authorize/", "scope_separator" => " "),
			"api"					=> Array("base_url" => "https://api.instagram.com/v1", "token_auth" => true, "headers" => Array("User-Agent" => "OAuth 2.0 Client https://github.com/samuelthomas2774/oauth-client"), "callback" => null),
			"requests"				=> Array("/oauth/token" => "https://api.instagram.com/oauth/access_token", "/oauth/token:response" => "json", "/oauth/token/debug" => "https://api.instagram.com/oauth/access_tokens"),
			"errors"				=> Array("throw" => true)
		);
		
		// function api(). Makes a new request to the server's API.
		public function api($method, $url, $params = Array(), $headers = Array(), $auth = false) {
			if(!isset($params["access_token"])) $params["access_token"] = $this->accessToken();
			return parent::api($method, $url, $params, $headers, $auth);
		}
		
		// function userProfile(). Fetches the current user's profile.
		public function userProfile() {
			$request = $this->api("GET", "/users/self/");
			
			$request->execute();
			return $request->responseObject()->data;
		}
	}
	
