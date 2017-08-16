<?php
/**
 * ownCloud - mailer
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Hugo Gonzalez Labrador (CERN) <hugo.gonzalez.labrador@cern.ch>
 * @copyright Hugo Gonzalez Labrador (CERN) 2017
 */

namespace OCA\Mailer\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\Share\Exceptions\ShareNotFound;

class PageController extends Controller {


	private $userId;
	private $shareManager;
	private $mailer;
	private $groupManager;

	public function __construct($AppName, IRequest $request, $UserId){
		parent::__construct($AppName, $request);
		$this->userManager = \OC::$server->getUserManager();
		$this->userId = $UserId;
		$this->shareManager = \OC::$server->getShareManager();
		$this->mailer = \OC::$server->getMailer();
		$this->userInfo = $this->userManager->get($this->userId);
		$this->groupManager = \OC::$server->getGroupManager();
	}

	/**
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 */
	public function sendMail($id, $shareType, $recipient) {
		$shareType = (int)($shareType);
		$share = $this->shareManager->getShareById("ocinternal:" . $id);

		if(!$share) {
			throw new ShareNotFound("share not found for sending mail");
		}

		if($share->getShareOwner() !== $this->userId) {
			throw new ShareNotFound("user is not allowed to send mails");
		}

		$shareInfo = $share->getNode()->get("")->stat();
		if(!$shareInfo) {
			throw new ShareNotFound("share is dangling");
		}

		$directURL = \OCP\Util::linkToAbsolute('index.php/apps','files', ['dir'=> $share->getTarget()]);
		$sharedWithMeURL = \OCP\Util::linkToAbsolute('index.php/apps', 'files', ['dir' => '/', 'view' => 'sharingin']);
		$serviceURL = \OCP\Util::linkToAbsolute();


		if($shareType === \OCP\Share::SHARE_TYPE_USER) {
			$recipientInfo = $this->userManager->get($recipient);
			if(!$recipientInfo) {
				throw new ShareNotFound("recipient does not exist");
			}
			$subject = sprintf("%s shared the folder '%s' with you", $this->userInfo->getDisplayName(), basename($shareInfo['eos.file']), $recipientInfo->getUID());
			$htmlBody = sprintf(
				"<p>Dear user,</p><p><b>%s</b> shared the folder <q><b>%s</b></q> with your account <b>%s</b>.</p>"
				. "<p>If you are logged in as <b>%s</b> you can click <a href='%s'>here</a> to access the shared folder, else you can go to <a href='%s'>%s</a> to find the shared folder under the 'Shared with me' tab.</p>"
				. "<p>To synchronise this folder please follow the instructions at <a href=''>this KB</a> using the following path: <p><code>%s</code></p></p>"
				. "<p>Best regards,</br>CERNBox Team</p>",
				$this->userInfo->getDisplayName(), basename($shareInfo['eos.file']), $recipientInfo->getUID(), $recipientInfo->getUID(), $directURL, $sharedWithMeURL, $serviceURL, $shareInfo['eos.file']);
			$textBody = sprintf("Hola paquito");
			$message = $this->mailer->createMessage();
			$message->setSubject($subject);
			$message->setTo([$recipientInfo->getEMailAddress() => $recipientInfo->getDisplayName()]);
			$message->setHtmlBody($htmlBody);
			$message->setPlainBody($textBody);
			$message->setFrom(['cernbox-noreply@cern.ch']);
			$message->setReplyTo([$recipientInfo->getEMailAddress()]);
			$this->mailer->send($message);
			return new DataResponse(['message' => 'Mail informing about the share sent to ' . $recipientInfo->getEMailAddress()]);
		}

		if ($shareType === \OCP\Share::SHARE_TYPE_GROUP) {
			$recipientInfo = $this->groupManager->get($recipient);
			if(!$recipientInfo) {
				throw new ShareNotFound("recipient does not exist");
			}

			$users = $recipientInfo->getUsers();
			foreach($users as $user) {
				$subject = sprintf("%s shared the folder '%s' with you through the e-group '%s'", $this->userInfo->getDisplayName(), basename($shareInfo['eos.file']), $recipient);
				$htmlBody = sprintf(
					"<p>Dear user,</p><p><b>%s</b> shared the folder <q><b>%s</b></q> with the e-group <b>%s</b>, which your account <b>%s</b> is member of it.</p>"
					. "<p>If you are logged in as <b>%s</b> you can click <a href='%s'>here</a> to access the shared folder, else you can go to <a href='%s'>%s</a> and click the tab 'Shared with me' to see the shared folder</p>"
					. "<p>To synchronise this folder please follow the instructions at <a href=''>this KB</a> using the following path: <p><code>%s</code></p></p>"
					. "<p>Best regards,</br>CERNBox Team</p>",
					$this->userInfo->getDisplayName(), basename($shareInfo['eos.file']), $recipient, $user->getUID(), $user->getUID(), $directURL, $sharedWithMeURL, $serviceURL, $shareInfo['eos.file']);
				$textBody = sprintf("Hola paquito");
				$message = $this->mailer->createMessage();
				$message->setSubject($subject);
				$message->setTo([$user->getEMailAddress() => $user->getDisplayName()]);
				$message->setHtmlBody($htmlBody);
				$message->setPlainBody($textBody);
				$message->setFrom(['cernbox-noreply@cern.ch']);
				$message->setReplyTo([$this->userInfo->getEMailAddress()]);
				$this->mailer->send($message);
				return new DataResponse(['message' => 'Mail informing about the share sent to ' . count($users) . ' members of the e-group ' . $recipient]);
			}
		}
	}

}