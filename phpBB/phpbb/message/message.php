<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

namespace phpbb\message;

use phpbb\messenger\method\messenger_interface;

/**
* Class message
* Holds all information for an email and sends it in the end
*/
class message
{
	/** @var string */
	protected $server_name;

	/** @var string */
	protected $subject = '';
	/** @var string */
	protected $body = '';
	/** @var string */
	protected $template = '';
	/** @var array */
	protected $template_vars = array();

	/** @var string */
	protected $sender_ip = '';
	/** @var string */
	protected $sender_name = '';
	/** @var string */
	protected $sender_address = '';
	/** @var string */
	protected $sender_lang = '';
	/** @var string|int */
	protected $sender_id = '';
	/** @var string */
	protected $sender_username = '';
	/** @var string */
	protected $sender_jabber = '';
	/** @var int */
	protected $sender_notify_type = messenger_interface::NOTIFY_EMAIL;

	/** @var array */
	protected $recipients;

	/**
	* Construct
	*
	* @param string $server_name	Used for AntiAbuse header
	*/
	public function __construct($server_name)
	{
		$this->server_name = $server_name;
	}

	/**
	* Set the subject of the email
	*
	* @param string $subject
	* @return void
	*/
	public function set_subject($subject)
	{
		$this->subject = $subject;
	}

	/**
	* Set the body of the email text
	*
	* @param string $body
	* @return void
	*/
	public function set_body($body)
	{
		$this->body = $body;
	}

	/**
	* Set the name of the email template to use
	*
	* @param string $template
	* @return void
	*/
	public function set_template($template)
	{
		$this->template = $template;
	}

	/**
	* Set the array with the "template" data for the email
	*
	* @param array $template_vars
	* @return void
	*/
	public function set_template_vars($template_vars)
	{
		$this->template_vars = $template_vars;
	}

	/**
	* Add a recipient from \phpbb\user
	*
	* @param array $user
	* @return void
	*/
	public function add_recipient_from_user_row(array $user)
	{
		$this->add_recipient(
			$user['username'],
			$user['user_email'],
			$user['user_lang'],
			$user['user_notify_type'],
			$user['username'],
			$user['user_jabber']
		);
	}

	/**
	* Add a recipient
	*
	* @param string $recipient_name		Displayed sender name
	* @param string $recipient_address	Email address
	* @param string $recipient_lang
	* @param int $recipient_notify_type	Used notification methods (Jabber, Email, ...)
	* @param string $recipient_username	User Name (used for AntiAbuse header)
	* @param string $recipient_jabber
	* @return void
	*/
	public function add_recipient($recipient_name, $recipient_address, $recipient_lang, $recipient_notify_type = messenger_interface::NOTIFY_EMAIL, $recipient_username = '', $recipient_jabber = '')
	{
		$this->recipients[] = array(
			'name'			=> $recipient_name,
			'user_email'	=> $recipient_address,
			'lang'			=> $recipient_lang,
			'username'		=> $recipient_username,
			'user_jabber'	=> $recipient_jabber,
			'notify_type'	=> $recipient_notify_type,
			'to_name'		=> $recipient_name,
		);
	}

	/**
	* Set the senders data from \phpbb\user object
	*
	* @param \phpbb\user $user
	* @return void
	*/
	public function set_sender_from_user($user)
	{
		$this->set_sender(
			$user->ip,
			$user->data['username'],
			$user->data['user_email'],
			$user->lang_name,
			$user->data['user_id'],
			$user->data['username'],
			$user->data['user_jabber']
		);

		$this->set_sender_notify_type($user->data['user_notify_type']);
	}

	/**
	* Set the senders data
	*
	* @param string $sender_ip
	* @param string $sender_name		Displayed sender name
	* @param string $sender_address		Email address
	* @param string $sender_lang
	* @param int $sender_id				User ID
	* @param string $sender_username	User Name (used for AntiAbuse header)
	* @param string $sender_jabber
	* @return void
	*/
	public function set_sender($sender_ip, $sender_name, $sender_address, $sender_lang = '', $sender_id = 0, $sender_username = '', $sender_jabber = '')
	{
		$this->sender_ip = $sender_ip;
		$this->sender_name = $sender_name;
		$this->sender_address = $sender_address;
		$this->sender_lang = $sender_lang;
		$this->sender_id = $sender_id;
		$this->sender_username = $sender_username;
		$this->sender_jabber = $sender_jabber;
	}

	/**
	* Which notification type should be used? Jabber, Email, ...?
	*
	* @param int $sender_notify_type
	* @return void
	*/
	public function set_sender_notify_type($sender_notify_type)
	{
		$this->sender_notify_type = $sender_notify_type;
	}

	/**
	* Ok, now the same email if CC specified, but without exposing the user's email address
	*
	* @return void
	*/
	public function cc_sender()
	{
		if (!count($this->recipients))
		{
			trigger_error('No email recipients specified');
		}
		if (!$this->sender_address)
		{
			trigger_error('No email sender specified');
		}

		$this->recipients[] = array(
			'lang'			=> $this->sender_lang,
			'user_email'	=> $this->sender_address,
			'name'			=> $this->sender_name,
			'username'		=> $this->sender_username,
			'user_jabber'	=> $this->sender_jabber,
			'notify_type'	=> $this->sender_notify_type,
			'to_name'		=> $this->recipients[0]['to_name'],
		);
	}

	/**
	* Send the email
	*
	* @param \phpbb\di\service_collection $messenger
	* @param string $contact
	* @return void
	*/
	public function send(\phpbb\di\service_collection $messenger, $contact)
	{
		if (!count($this->recipients))
		{
			return;
		}

		foreach ($this->recipients as $recipient)
		{
			/** @psalm-suppress InvalidTemplateParam */
			$messenger_collection_iterator = $messenger->getIterator();

			/**
			 * @var messenger_interface $messenger_method
			 * @psalm-suppress UndefinedMethod
			 */
			foreach ($messenger_collection_iterator as $messenger_method)
			{
				$messenger_method->set_use_queue(false);
				if ($messenger_method->get_id() == $recipient['notify_type'] || $recipient['notify_type'] == $messenger_method::NOTIFY_BOTH)
				{
					$messenger_method->template($this->template, $recipient['lang']);
					$messenger_method->set_addresses($recipient);
					$messenger_method->reply_to($this->sender_address);

					$messenger_method->header('X-AntiAbuse', 'Board servername - ' . $this->server_name);
					$messenger_method->header('X-AntiAbuse', 'User IP - ' . $this->sender_ip);
					if ($this->sender_id)
					{
						$messenger_method->header('X-AntiAbuse', 'User_id - ' . $this->sender_id);
					}

					if ($this->sender_username)
					{
						$messenger_method->header('X-AntiAbuse', 'Username - ' . $this->sender_username);
					}

					$messenger_method->subject(html_entity_decode($this->subject, ENT_COMPAT));

					$messenger_method->assign_vars([
						'BOARD_CONTACT'	=> $contact,
						'TO_USERNAME'	=> html_entity_decode($recipient['to_name'], ENT_COMPAT),
						'FROM_USERNAME'	=> html_entity_decode($this->sender_name, ENT_COMPAT),
						'MESSAGE'		=> html_entity_decode($this->body, ENT_COMPAT),
					]);

					if (count($this->template_vars))
					{
						$messenger_method->assign_vars($this->template_vars);
					}

					$messenger_method->send();
				}
			}
		}
	}
}
