<?php
/**
 * Craft Trade Account Notifications plugin for Craft CMS 3.x
 * Send notifications to the client and user on changing user group.
 * @link      https://www.headjam.com.au
 * @copyright Copyright (c) 2020 Ben Norman
 */

namespace headjam\crafttradeaccountnotifications;

use Craft;
use craft\base\Plugin;
use craft\services\Users;
use craft\events\UserEvent;
use craft\events\UserGroupsAssignEvent;
use craft\web\View;

use yii\base\Event;

/**
 *
 * @author    Ben Norman
 * @package   CraftTradeAccountNotifications
 * @since     1.0.0
 *
 */
class CraftTradeAccountNotifications extends Plugin
{
  // Static Properties
  // =========================================================================
  /**
   * Static property that is an instance of this plugin class so that it can be accessed via
   * CraftTradeAccountNotifications::$plugin
   * @var CraftTradeAccountNotifications
   */
  public static $plugin;



  // Public Properties
  // =========================================================================
  /**
   * To execute your plugin’s migrations, you’ll need to increase its schema version.
   * @var string
   */
  public $schemaVersion = '1.0.0';

  /**
   * Set to `true` if the plugin should have a settings view in the control panel.
   * @var bool
   */
  public $hasCpSettings = false;

  /**
   * Set to `true` if the plugin should have its own section (main nav item) in the control panel.
   * @var bool
   */
  public $hasCpSection = false;



  // Private Properties
  // =========================================================================
  private $tradeAccountNotificationEmail;
  private $tradeAccountGroupId;



  // Public Methods
  // =========================================================================
  /**
   * A customer logger for the plugin.
   */
  public static function log($message){
    Craft::getLogger()->log($message, \yii\log\Logger::LEVEL_INFO, 'craft-trade-account-notifications');
  }

  /**
   * Set our $plugin static property to this class so that it can be accessed via
   * CraftTradeAccountNotifications::$plugin
   *
   * Called after the plugin class is instantiated; do any one-time initialization
   * here such as hooks and events.
   * If you have a '/vendor/autoload.php' file, it will be loaded for you automatically;
   * you do not need to load it in your init() method.
   *
   */
  public function init()
  {
    parent::init();
    self::$plugin = $this;

    // Get relevant env settings
    $this->tradeAccountNotificationEmail = getenv('TRADE_ACCOUNT_NOTIFICATION_EMAIL');
    $this->tradeAccountGroupId = getenv('TRADE_ACCOUNT_GROUP_ID');

    // Init the customer logger
    $fileTarget = new \craft\log\FileTarget([
      'logFile' => Craft::getAlias('@storage/logs/craftTradeAccountNotifications.log'),
      'categories' => ['craft-trade-account-notifications']
    ]);
    Craft::getLogger()->dispatcher->targets[] = $fileTarget;

    // On user activation event, check if an email needs to be
    // sent to the client for trade account approval.
    Event::on(
      Users::class,
      Users::EVENT_AFTER_ACTIVATE_USER,
      function (UserEvent $e) {
        CraftTradeAccountNotifications::log(json_encode($e));
      }
    );

    // On user group change event, check if the user is
    // now in the Trade Account group and, if so, notify them.
    Event::on(
      Users::class,
      Users::EVENT_BEFORE_ASSIGN_USER_TO_GROUPS,
      function (UserGroupsAssignEvent $e) {
        if ($this->tradeAccountGroupId && $e->groupIds && in_array($this->tradeAccountGroupId, $e->groupIds)) {
          CraftTradeAccountNotifications::sendUserNotification($e->userId);
        }
      }
    );
  }

  // Private Methods
  // =========================================================================
  /** 
   * Send an email.
   * @param String $html - The html to send.
   * @param String $subject - The email subject.
   * @param String $sendTo - The email address to send it to.
   */
  private function sendMail(string $html, string $subject, string $sendTo) {
    if ($html && $subject && $sendTo) {
        return Craft::$app
            ->getMailer()
            ->compose()
            ->setTo($sendTo)
            ->setSubject($subject)
            ->setHtmlBody($html)
            ->send();
    }
  }
  /**
   * Send an email to the given user to let them know they now have a trade account.
   * @param Number $userId - The id of the user to notify.
   */
  private function sendUserNotification($userId) {
    $user = \craft\elements\User::find()->id($userId)->one();
    if ($user) {
      Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_SITE);
      $html = Craft::$app->getView()->renderTemplate('_emails/trade-account-approved.html');
      CraftTradeAccountNotifications::sendMail($html, 'Trade Account Approved', $user->email);
    }
  }
}
