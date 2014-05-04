<?php
class EditUserEmailJob extends AbstractUserJob
{
	const NEW_EMAIL = 'new-email';

	public function execute()
	{
		$user = $this->user;
		$newEmail = UserModel::validateEmail($this->getArgument(self::NEW_EMAIL));

		$oldEmail = $user->emailConfirmed;
		if ($oldEmail == $newEmail)
			return $user;

		if (Auth::getCurrentUser()->id == $user->id)
		{
			$user->emailUnconfirmed = $newEmail;
			$user->emailConfirmed = null;

			if (!empty($newEmail))
			{
				$this->sendEmail($user);
			}
		}
		else
		{
			$user->emailUnconfirmed = null;
			$user->emailConfirmed = $newEmail;
		}

		UserModel::save($user);

		LogHelper::log('{user} changed {subject}\'s e-mail to {mail}', [
			'user' => TextHelper::reprUser(Auth::getCurrentUser()),
			'subject' => TextHelper::reprUser($user),
			'mail' => $newEmail]);

		return $user;
	}

	public function requiresPrivilege()
	{
		return
		[
			Privilege::ChangeUserAccessRank,
			Access::getIdentity($this->user),
		];
	}

	//todo: change to private once finished refactors to UserController
	public function sendEmail($user)
	{
		$regConfig = getConfig()->registration;

		if (!$regConfig->confirmationEmailEnabled)
		{
			$user->emailUnconfirmed = null;
			$user->emailConfirmed = $user->emailUnconfirmed;
			return;
		}

		$mail = new Mail();
		$mail->body = $regConfig->confirmationEmailBody;
		$mail->subject = $regConfig->confirmationEmailSubject;
		$mail->senderName = $regConfig->confirmationEmailSenderName;
		$mail->senderEmail = $regConfig->confirmationEmailSenderEmail;
		$mail->recipientEmail = $user->emailUnconfirmed;

		return Mailer::sendMailWithTokenLink(
			$user,
			['UserController', 'activationAction'],
			$mail);
	}
}
