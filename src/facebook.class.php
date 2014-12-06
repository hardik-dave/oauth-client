<?php
	/* class OAuthFacebook
	 * /src/facebook.class.php
	 */
	require_once 'oauth.class.php';
	
	class OAuthFacebook extends OAuth {
		// Options. These shouldn't be modified here, but using the OAuth::options() function.
		public $options = Array(
			"session_prefix"		=> "facebook_",
			"dialog"				=> Array("base_url" => "https://www.facebook.com/dialog/oauth"),
			"api"					=> Array("base_url" => "https://graph.facebook.com/v2.2", "token_auth" => true),
			"requests"				=> Array("/oauth/token" => "/oauth/access_token", "/oauth/token/debug" => "/debug_token"),
			"errors"				=> Array("throw" => true)
		);
		
		// function getAccessTokenFromCode(). Exchanges the code for an access token.
		public function getAccessTokenFromCode($redirect_url, $code = null, $state = true) {
			// Check if redirect_url is a url. The redirect_url should be exactly the same as the redirect_url used in the login dialog. (So really, this should just be the same as the current url.)
			if(!filter_var($redirect_url, FILTER_VALIDATE_URL)) throw new Exception(__CLASS__ . "::" . __METHOD__ . "(): \$redirect_url must be a valid url.");
			
			// Check if code is a string or null.
			if(is_string($code)) $code = trim($code);
			elseif(($code == null) && isset($_GET["code"])) $code = trim($_GET["code"]);
			else throw new Exception(__CLASS__ . "::" . __METHOD__ . "(): \$code must be a string.");
			
			// Check state if required.
			if( ($state != false) && ( // Check state?
				($this->session("state") == null) || // State is not set: trigger error.
				($this->session("state") !=
					($state == true ? $_GET["state"] : $state)
				) // State does not match $_GET["state"] or $state: trigger error.
			)) {
				// Invalid state parameter.
				$this->sessionDelete("state");
				$facebook->error = "Invalid state parameter.";
				throw new Exception("Invalid state parameter.");
			}
			
			// Unset the access token.
			$this->token = null;
			
			// Example request: GET /oauth/token?client_id={app_id}&client_secret={app_secret}&redirect_uri={redirect_url}&code={code}
			$request = $this->api("POST", $this->options("requests")["/oauth/token"], Array(
				"client_id"				=> $this->app["id"],
				"client_secret"			=> $this->app["secret"],
				"redirect_uri"			=> $redirect_url,
				"code"					=> $code,
				"grant_type"			=> "authorization_code"
			), null, true);
			
			$request->execute();
			parse_str($request->response(), $response);
			$this->accessToken($response["access_token"]);
		}
		
		// function validateAccessToken(). Verifies an access token.
		public function validateAccessToken($access_token = null) {
			// Check if access_token is string.
			if(!is_string($access_token)) $access_token = $this->token;
			
			// Example request: GET /oauth/token/debug?access_token={access_token}
			$request = $this->api("GET", $this->options("requests")["/oauth/token/debug"], Array(
				"access_token"			=> $access_token
			));
			
			try { $request->execute(); parse_str($request->response(), $response); }
			catch(Exception $e) { return false; }
			if(isset($response->error)) return false;
			
			if($response["expires_in"] <= 0) return false;
			return true;
		}
		
		// function userProfile(). Fetches the current user's profile.
		public function userProfile($fields = Array()) {
			// Check if fields is an array.
			if(!is_array($fields)) $fields = Array();
			
			$request = $this->api("GET", "/me", Array("fields" => implode(",", $fields)));
			
			$request->execute();
			return $request->responseObject();
		}
		
		// function profilePicture(). Fetches the current user's profile.
		public function profilePicture($width = 50, $height = 50) {
			// Check if width and height are integers.
			if(!is_integer($width) && !is_numeric($width)) $width = 50;
			if(!is_integer($height) && !is_numeric($height)) $height = 50;
			
			$request = $this->api("GET", "/me", Array("fields" => "id,picture.width({$width}).height({$height})"));
			
			$request->execute();
			$response = $request->responseObject();
			$picture = $response->picture->data;
			
			// Build an <img> tag.
			$picture->tag = "<img src=\"";
			$picture->tag .= $picture->url;
			$picture->tag .= "\" style=\"width:";
			$picture->tag .= $picture->width;
			$picture->tag .= "px;height:";
			$picture->tag .= $picture->height;
			$picture->tag .= "px;\" />";
			
			return $picture;
		}
		
		// function permissions(). Fetches the permissions and returns them in an array.
		public function permissions($rearrange = true) {
			$request = $this->api("GET", "/me/permissions");
			
			$request->execute();
			$response = $request->responseObject();
			
			if($rearrange == false) {
				return $response;
			} else {
				$permissions = new stdClass();
				foreach($response->data as $p) {
					$status = $p->status;
					if($status == "granted") $granted = true; else $granted = false;
					$permissions->{$p->permission} = new stdClass(); // Array("granted" => $granted, "status" => $p->status);
					$permissions->{$p->permission}->granted = $granted;
					$permissions->{$p->permission}->status = $p->status;
				}
				
				return $permissions;
			}
		}
		
		// function permission(). Checks if the permission has been granted. Returns true if true, false if false.
		public function permission($permission) {
			$permissions = $this->permissions();
			
			if(isset($permissions->{$permission}) && ($permissions->{$permission}->granted == true)) {
				return true;
			} else {
				return false;
			}
		}
		
		// function ids(). Fetches the user ids for other apps the user has authorised and are linked to the same business.
		public function ids($rearrange = true) {
			$request = $this->api("GET", "/me/ids_for_business");
			
			$request->execute();
			$response = $request->responseObject();
			
			if($rearrange == false) {
				return $response;
			} else {
				$ids = new stdClass();
				foreach($response->data as $id) {
					$ids->{$id->app->id} = new stdClass(); // Array("app_name" => $id->app->name, "app_namespace" => $id->app->namespace, "app_id" => $id->app->id, "user_id" => $id->id);
					$ids->{$id->app->id}->app_name = $id->app->name;
					$ids->{$id->app->id}->app_namespace = $id->app->namespace;
					$ids->{$id->app->id}->app_id = $id->app->id;
					$ids->{$id->app->id}->user_id = $id->id;
				}
				
				return $ids;
			}
		}
		
		// function deauth(). De-authorises the application, or removes one permission. Once this is called, the user will have to authorise the application again using the Facebook Login Dialog.
		public function deauth($permission = null) {
			$request = $this->api("DELETE", "/me/permissions" . (is_string($permission) ? "/" . $permission : ""));
			
			$request->execute();
			$response = $request->responseObject();
			
			if($response->success == true) return true;
			else return false;
		}
		
		// function pages(). Fetches a list of all the pages the user manages. Requires the manage_pages permission.
		public function pages($rearrange = true) {
			$permissions = $this->permissions(); if(!isset($permissions->manage_pages) || ($permissions->manage_pages->status == "declined"))
				throw new Exception("Facebook::pages(): User has declined the manage_pages permission.");
			
			$request = $this->api("GET", "/me/accounts");
			
			$request->execute();
			$response = $request->responseObject();
			
			if($rearrange == false) {
				return $response;
			} else {
				$pages = new stdClass();
				foreach($response->data as $page) {
					$pages->{$page->id} = new stdClass();
					$pages->{$page->id}->id = $page->id;
					$pages->{$page->id}->name = $page->name;
					$pages->{$page->id}->access_token = $page->access_token;
					$pages->{$page->id}->permissions = $page->perms;
					$pages->{$page->id}->category = $page->category;
					$pages->{$page->id}->category_list = $page->category_list;
				}
				
				return $pages;
			}
		}
		
		// function post(). Posts something to the user's timeline. Requires the publish_actions permission.
		public function post($post2 = Array(), $returnid = false) {
			$permissions = $this->permissions(); if(!isset($permissions->publish_actions) || ($permissions->publish_actions->status == "declined"))
				throw new Exception("Facebook::post(): User has declined the publish_actions permission.");
			
			$post = Array();
			if(isset($post2["message"])) $post["message"] = $post2["message"];
			if(isset($post2["link"])) $post["link"] = $post2["link"];
			if(isset($post2["place"])) $post["place"] = $post2["place"];
			if(isset($post2["place"]) && isset($post2["tags"])) $post["tags"] = $post2["tags"];
			
			$request = $this->api("POST", "/me/feed", $post);
			
			$request->execute();
			$response = $request->responseObject();
			
			if(isset($response->id)) {
				if($returnid == true) return $response->id;
				else return true;
			} else {
				return false;
			}
		}
	}
	
