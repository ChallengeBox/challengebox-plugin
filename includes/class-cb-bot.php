<?php

use Frlnc\Slack\Http\SlackResponseFactory;
use Frlnc\Slack\Http\CurlInteractor;
use Frlnc\Slack\Core\Commander;

class SlackError extends Exception {}

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

	private function handle_slack_response($response) {
		if ($response->getStatusCode() == 200 && $response->getBody()['ok']) {
			$this->debug("slack call successfull");
		} else {
			throw new SlackError(json_encode($response), $response->getStatusCode());
		}
	}

	public function debug($thing) {
		WP_CLI::debug(var_export($thing, true));
	}

	public function text_mentions_me($text) {
		return preg_match('/' . preg_quote("<@$this->user>") . '/i', $text);
	}

	public function process_message($text, $channel, $user, $ts, $team) {
		if ($user === $this->user) return; // Don't talk to myself

		// If the text mentions me, process it as a command
		if ($this->text_mentions_me($text)) {
			//$clean = preg_replace("/[[:punct:]]+/", " ", $text);
			//$this->debug($clean);
			$email_preserved = preg_replace("/<mailto:[^|]+\|([^>]+)>/i", "$1", $text);
			//$this->debug($email_preserved);

			$tokens = preg_split('#[[:punct:]]+|\s+#', $email_preserved, null, PREG_SPLIT_NO_EMPTY);
			$raw_tokens = preg_split('#\s+#', $email_preserved, null, PREG_SPLIT_NO_EMPTY);
			$this->debug($tokens);
			$this->debug($raw_tokens);
			$after_me = array_slice($tokens, array_search("$this->user", $tokens) + 1);
			$after_me_raw = array_slice($raw_tokens, array_search("$this->user", $tokens) + 1);

			$command = current($after_me);
			$args = array_slice($after_me_raw, 1);
			$this->debug(array(
				'command' => $command,
				'args' => $args,
			));

			// Parse and dispatch command
			$command_func = 'command_' . $command;
			if (method_exists($this, $command_func)) {
				try {
					$this->$command_func($args, $text, $channel, $user, $ts, $team);
				} catch (Exception $e) {
					$this->debug($e);
				}
			} else {
				$this->handle_slack_response($this->slack->execute('chat.postMessage', array(
					'channel'   => $channel,
					'text'      => ($command ? "I don't know how to `$command`." : "Hi!") . " Basically you can just ask me to `find PERSON`.",
					'mrkdwn'    => true,
					'as_user'   => true,
					'token'     => $this->token,
				)));
			}
		}
	}

	public function post_simple_message($channel, $message) {
			$this->handle_slack_response($this->slack->execute('chat.postMessage', array(
				'channel' => $channel, 'text' => $message,
				'as_user' => true, 'token' => $this->token,
			)));
	}

	public function post_predictions($channel) {
		$predictions = CBWoo::get_order_predictions();
		$rows = array();
		foreach ($predictions as $row) {
			$rows[] = "*$row->month* " . number_format($row->count);
		}
		$table = implode("\n", $rows);
		$message = "Order prediction for next month's boxes:\n$table";
		$this->post_simple_message($channel, $message);
	}

	public function command_predict($args, $text, $channel, $user, $ts, $team) {
		$this->post_predictions($channel);
	}

	public function command_find($args, $text, $channel, $user, $ts, $team) {
		$query = implode(' ', array_slice($args, 0, 3));
		$this->debug($args);
		$guesser = new UserGuesser($query);
		$guesses = array_filter($guesser->guess(), function($guess) { return $guess->rank; });
		$this->debug($guesses);
		if (sizeof($guesses)) {

			$max_results = 5;
			$attachments = array();
			foreach (array_slice($guesses, 0, $max_results) as $guess) {
				$edit = "user <https://www.getchallengebox.com/wp-admin/user-edit.php?user_id=$guess->id|#$guess->id>";
				$subs = "<https://www.getchallengebox.com/wp-admin/edit.php?post_status=all&post_type=shop_subscription&_customer_user=$guess->id|Subscriptions>";
				$ords = "<https://www.getchallengebox.com/wp-admin/edit.php?post_status=all&post_type=shop_order&_customer_user=$guess->id|Orders>";
				$title = "$guess->first_name $guess->last_name";
				// Add on billing name if it differs
				if (($guess->billing_first_name || $guess->billing_last_name)
						&& ($guess->billing_first_name !== $guess->first_name || $guess->billing_last_name !== $guess->last_name)) {
								$title .= " (Bill to: $guess->billing_first_name $guess->billing_last_name)";
				}
				// Add on shipping name if it differs
				if (($guess->shipping_first_name || $guess->shipping_last_name)
						&& ($guess->shipping_first_name !== $guess->first_name || $guess->shipping_last_name !== $guess->last_name)) {
						if ($guess->shipping_first_name == $guess->billing_first_name && $guess->shipping_last_name == $guess->billing_last_name) {
							$title = preg_replace('/Bill to/', 'Bill+ship to', $title);
						} else {
							$title .= " (Ship to: $guess->shipping_first_name $guess->shipping_last_name)";
						}
				}
				$email = "$guess->email";
				if ($guess->billing_email && $guess->billing_email !== $guess->email) {
					$email .= " (Bill to: $guess->billing_email)";
				}
				$attachments[] = array(
					'fallback' => "$guess->first_name $guess->last_name <$guess->email>",
					'title' => $title,
					'text' => "$email | $edit | $subs | $ords",
					//'mrkdwn' => true,
				);
			}

			$this->handle_slack_response($this->slack->execute('chat.postMessage', array(
				'channel'   => $channel,
				'attachments' => json_encode($attachments),
				'as_user'   => true,
				'token'     => $this->token,
			)));

			if (sizeof($guesses) > $max_results && $guesser->first_name_guess == $guesser->last_name_guess) {
				$number_left = sizeof($guesses) - $max_results;
				$message = "Hey, there are $number_left more people that sound like they could be who you're looking for. I tried to put the most relevent ones here. Can you be more specific? Maybe use a last name or email?";
				/*
				$attachments[] = array(
					'fallback' => $message,
					'text' => $message,
					//'mrkdwn' => true,
				);
				*/
				$this->handle_slack_response($this->slack->execute('chat.postMessage', array(
					'channel'   => $channel,
					'text'      => $message,
					'as_user'   => true,
					'token'     => $this->token,
				)));
			}
		} else {
			$this->handle_slack_response($this->slack->execute('chat.postMessage', array(
				'channel'   => $channel,
				'text'      => "I couldn't find anybody. Maybe try alternate spellings or include an email?",
				'mrkdwn'    => true,
				'as_user'   => true,
				'token'     => $this->token,
			)));
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
			$bot->debug($data);
			if ($data['type']) {
				$bot->process_message($data['text'], $data['channel'], $data['user'], $data['ts'], $data['team']);
			}
		});

		$client->connect()->then(function () use ($bot) {
			echo "Connected!\n";
			$bot->post_simple_message('@ryan', "I'm connected.");
		});

		$loop->run();
	}
}
