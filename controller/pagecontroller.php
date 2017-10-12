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
		$serviceURL = \OCP\Util::getServerProtocol() . "://" . \OCP\Util::getServerHost();


		if($shareType === \OCP\Share::SHARE_TYPE_USER) {
			$recipientInfo = $this->userManager->get($recipient);
			if(!$recipientInfo) {
				throw new ShareNotFound("recipient does not exist");
			}
			$subject = sprintf("%s shared the folder '%s' with you", $this->userInfo->getDisplayName(), basename($shareInfo['eos.file']), $recipientInfo->getUID());

			$htmlBody = sprintf(
				"<p><b>%s</b> shared the folder <q><b>%s</b></q> with you (<b>%s</b>).</p>"
				. "<p>If you are logged in as <b>%s</b> you can go to <a href='%s'>CERNBox</a>  and click the tab 'Shared with you' to find the shared folder.</p>"
				. "<p>If you want to sync the share in your desktop, add a new folder with this path (<a href='https://cern.service-now.com/service-portal/article.do?n=KB0003663'>FAQ</a>):</p>"
				. "<p><code>%s</code></p>"
				. "<p>Best regards,</br>CERNBox Team</p>",
				$this->userInfo->getDisplayName(), basename($share->getTarget()), $recipientInfo->getUID(), $recipientInfo->getUID(), $serviceURL, $shareInfo['eos.file']);
			$textBody = sprintf(
				"%s shared the folder %s with you (%s).\n"
				. "If you are logged in as %s you can go to '%s' and click the tab 'Shared with you' to find the shared folder.\n"
				. "If you want to sync the share in your desktop, add a new folder with this path: %s\n\n"
				. "Best regards,\nCERNBox Team",
				$this->userInfo->getDisplayName(), basename($share->getTarget()), $recipientInfo->getUID(), $recipientInfo->getUID(), $serviceURL, $shareInfo['eos.file']);

			$message = $this->mailer->createMessage();
			$message->setSubject($subject);
			$message->setTo([$recipientInfo->getEMailAddress() => $recipientInfo->getDisplayName()]);
			$message->setHtmlBody($htmlBody);
			$message->setPlainBody($textBody);
			$message->setFrom(['cernbox-noreply@cern.ch']);
			$message->setReplyTo([$recipientInfo->getEMailAddress()]);
			$this->mailer->send($message);
			$resMessage = sprintf("An email informing about the share has been sent to %s.", $recipientInfo->getEMailAddress());
			return new DataResponse(['message' => $resMessage]);
		}

		if ($shareType === \OCP\Share::SHARE_TYPE_GROUP) {
			$recipientInfo = $this->groupManager->get($recipient);
			if(!$recipientInfo) {
				throw new ShareNotFound("recipient does not exist");
			}
			
			$recipientAddress = $recipient . "@cern.ch";
			$subject = sprintf("%s shared the folder '%s' with you through the e-group '%s'", $this->userInfo->getDisplayName(), basename($shareInfo['eos.file']), $recipient);
			$htmlBody = sprintf(
				"<p><b>%s</b> shared the folder <q><b>%s</b></q> with the e-group <b>%s</b>, which you are member of it.</p>"
				. "<p>If you login to <a href='%s'>CERNBox</a>, click the tab 'Shared with you' to see the shared folder</p>"
				. "<p>If you want to sync the share in your desktop, add a new folder with this path (<a href='https://cern.service-now.com/service-portal/article.do?n=KB0003663'>FAQ</a>):</p>"
				. "<p><code>%s</code></p>"
				. "<p>Best regards,</br>CERNBox Team</p>",
				$this->userInfo->getDisplayName(), basename($share->getTarget()), $recipient, $serviceURL, $shareInfo['eos.file']);
			$textBody = sprintf(
				"%s shared the folder %s with the e-group %s, which you are member of it\n"
				. "If you login to %s you can click the tab 'Shared with you' to see the shared folder\n"
				. "If you want to sync the share in your desktop, add a new folder with this path: %s\n\n"
				. "Best regards,\nCERNBox Team",
				$this->userInfo->getDisplayName(), basename($share->getTarget()), $recipient, $serviceURL, $shareInfo['eos.file']);
			$message = $this->mailer->createMessage();
			$message->setSubject($subject);
			$message->setTo([$recipientAddress => $recipient]);
			$message->setHtmlBody($htmlBody);
			$message->setPlainBody($textBody);
			$message->setFrom(['cernbox-noreply@cern.ch']);
			$message->setReplyTo([$recipientAddress]);
			$this->mailer->send($message);
			$resMessage = sprintf("An email informing about the share has been sent to %s.\nBe aware that email may not arrive to the e-group mailing list if it is restricted.", $recipientAddress);
			return new DataResponse(['message' => $resMessage]);
		}
	}

}
