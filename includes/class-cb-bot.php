<?php

use Frlnc\Slack\Http\SlackResponseFactory;
use Frlnc\Slack\Http\CurlInteractor;
use Frlnc\Slack\Core\Commander;

/**
 * 
 */

class ChallengeBot {

	private $token;
	private $user;
	private $slack;

	public function __construct() {

		$this->token = 'xoxb-68010750118-LQZSXb9RATXjSoxqYAOpZ3Cw';
		$this->user  = 'U200AN23G';

		$interactor = new CurlInteractor;
		$interactor->setResponseFactory(new SlackResponseFactory);
		$this->slack = new Commander($this->token, $interactor);

	}

	public function text_mentions_me($text) {
		return preg_match('/' . preg_quote("<@$this->user>") . '/i', $text);
	}

	public function process_message($text, $channel, $user, $ts, $team) {
		if ($user === $this->user) return; // Don't talk to myself

		// If the text mentions me, process it as a command
		if ($this->text_mentions_me($text)) {
			//$clean = preg_replace("/[[:punct:]]+/", " ", $text);
			//var_dump($clean);
			$email_preserved = preg_replace("/<mailto:[^|]+\|([^>]+)>/i", "$1", $text);
			//var_dump($email_preserved);

			$tokens = preg_split('#[[:punct:]]+|\s+#', $email_preserved, null, PREG_SPLIT_NO_EMPTY);
			$raw_tokens = preg_split('#\s+#', $email_preserved, null, PREG_SPLIT_NO_EMPTY);
			var_dump($tokens);
			var_dump($raw_tokens);
			$after_me = array_slice($tokens, array_search("$this->user", $tokens) + 1);
			$after_me_raw = array_slice($raw_tokens, array_search("$this->user", $tokens) + 1);

			$command = current($after_me);
			$args = array_slice($after_me_raw, 1);
			var_dump(array(
				'command' => $command,
				'args' => $args,
			));

			// Parse and dispatch command
			$command_func = 'command_' . $command;
			if (method_exists($this, $command_func)) {
				try {
					$this->$command_func($args, $text, $channel, $user, $ts, $team);
				} catch (Exception $e) {
					var_dump($e);
				}
			}
		}
	}

	public function command_find($args, $text, $channel, $user, $ts, $team) {
		$query = implode(' ', $args);
		var_dump($args);
		$guesser = new UserGuesser($query);
		$guesses = array_filter($guesser->guess(), function($guess) { return $guess->rank; });
		var_dump($guesses);
		if (sizeof($guesses)) {

			$attachments = array();
			foreach ($guesses as $guess) {
				$edit = "<https://www.getchallengebox.com/wp-admin/user-edit.php?user_id=$guess->id|Edit>";
				$subs = "<https://www.getchallengebox.com/wp-admin/edit.php?post_status=all&post_type=shop_subscription&_customer_user=$guess->id|Subscriptions>";
				$ords = "<https://www.getchallengebox.com/wp-admin/edit.php?post_status=all&post_type=shop_order&_customer_user=$guess->id|Orders>";
				$attachments[] = array(
					'fallback' => "$guess->first_name $guess->last_name <$guess->email>",
					'title' => "$guess->first_name $guess->last_name",
					'text' => "$guess->email | $edit | $subs | $ords",
					//'mrkdwn' => true,
				);
			}

			$response = $this->slack->execute('chat.postMessage', array(
			    'channel'   => $channel,
			    //'text'      => "*$guess->first_name $guess->last_name* <$guess->email>",
			    //'mrkdwn'    => true,
			    'attachments' => json_encode($attachments),
			    'as_user'   => true,
			    'token'     => $this->token,
			));

			if ($response->getStatusCode() == 200 && $response->getBody()['ok']) {
				var_dump("posted response");
			} else {
			    var_dump($response);
			}	
		}
	}

	/**
	 * Core event loop for bot.
	 */
	public function run() {
		$loop = React\EventLoop\Factory::create();
		$client = new Slack\RealTimeClient($loop);
		$client->setToken($this->token);

		$bot = $this;
		$client->on('message', function ($data) use ($client, $bot) {
			try {
				var_dump($data);
				if ($data['type']) {
					$bot->process_message($data['text'], $data['channel'], $data['user'], $data['ts'], $data['team']);
				}
			} catch (Exception $e) {
				var_dump($e);
			}
		});

		$client->connect()->then(function () {
			echo "Connected!\n";
		});

		$loop->run();
	}
}
