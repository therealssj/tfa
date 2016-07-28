<?php
namespace Drupal\tfa\Plugin\TfaSetup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\tfa\Plugin\TfaLogin\TfaTrustedBrowser;
use Drupal\tfa\Plugin\TfaSetupInterface;
use Drupal\user\UserDataInterface;

/**
 * @TfaSetup(
 *   id = "tfa_trusted_browser_setup",
 *   label = @Translation("TFA Trusted Browser Setup"),
 *   description = @Translation("TFA Trusted Browser Setup Plugin")
 * )
 */
class TfaTrustedBrowserSetup extends TfaTrustedBrowser implements TfaSetupInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, UserDataInterface $user_data, EncryptionProfileManagerInterface $encryption_profile_manager, EncryptServiceInterface $encrypt_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $user_data, $encryption_profile_manager, $encrypt_service);
  }

  /**
   * {@inheritdoc}
   */
  public function getSetupForm(array $form, FormStateInterface $form_state) {
    $existing = $this->getTrustedBrowsers();
    $time = \Drupal::config('tfa.settings')->get('trust_cookie_expiration');
    $form['info'] = array(
      '#type' => 'markup',
      '#markup' => '<p>' . t("Trusted browsers are a method for simplifying login by avoiding verification code entry for a set amount of time, @time days from marking a browser as trusted. After !time days, to log in you'll need to enter a verification code with your username and password during which you can again mark the browser as trusted.", array('@time' => $time)) . '</p>',
    );
    // Present option to trust this browser if its not currently trusted.
    if (isset($_COOKIE[$this->cookieName]) && $this->trustedBrowser($_COOKIE[$this->cookieName]) !== FALSE) {
      $current_trusted = $_COOKIE[$this->cookieName];
    }
    else {
      $current_trusted = FALSE;
      $form['trust'] = array(
        '#type' => 'checkbox',
        '#title' => t('Trust this browser?'),
        '#default_value' => empty($existing) ? 1 : 0,
      );
      // Optional field to name this browser.
      $form['name'] = array(
        '#type' => 'textfield',
        '#title' => t('Name this browser'),
        '#maxlength' => 255,
        '#description' => t('Optionally, name the browser on your browser (e.g. "home firefox" or "office desktop windows"). Your current browser user agent is %browser', array('%browser' => $_SERVER['HTTP_USER_AGENT'])),
        '#default_value' => $this->getAgent(),
        '#states' => array(
          'visible' => array(
            ':input[name="trust"]' => array('checked' => TRUE),
          ),
        ),
      );
    }
    if (!empty($existing)) {
      $form['existing'] = array(
        '#type' => 'fieldset',
        '#title' => t('Existing browsers'),
        '#description' => t('Leave checked to keep these browsers in your trusted log in list.'),
        '#tree' => TRUE,
      );

      foreach ($existing as $browser_id => $browser) {
        $date_formatter = \Drupal::service('date.formatter');
        $vars = array(
          '!set' => $date_formatter->format($browser['created']),
        );

        if (isset($browser['last_used'])) {
          $vars['!time'] = $date_formatter->format($browser['last_used']);
        }

        if ($current_trusted == $browser_id) {
          $name = '<strong>' . t('@name (current browser)', array('@name' => $browser['name'])) . '</strong>';
        }
        else {
          $name = Html::escape($browser['name']);
        }

        if (empty($browser['last_used'])) {
          $message = t('Marked trusted !set', $vars);
        }
        else {
          $message = t('Marked trusted !set, last used for log in !time', $vars);
        }
        $form['existing']['trusted_browser_' . $browser_id] = array(
          '#type' => 'checkbox',
          '#title' => $name,
          '#description' => $message,
          '#default_value' => 1,
        );
      }
    }
    $form['actions']['save'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSetupForm(array $form, FormStateInterface $form_state) {
    return TRUE; // Do nothing, no validation required.
  }

  /**
   * {@inheritdoc}
   */
  public function submitSetupForm(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (isset($values['existing'])) {
      $count = 0;
      foreach ($values['existing'] as $element => $value) {
        $id = str_replace('trusted_browser_', '', $element);
        if (!$value) {
          $this->deleteTrusted($id);
          $count++;
        }
      }
      if ($count) {
        \Drupal::logger('tfa')->notice('Removed !num TFA trusted browsers during trusted browser setup', array('!num' => $count));
      }
    }
    if (!empty($values['trust']) && $values['trust']) {
      $name = '';
      if (!empty($values['name'])) {
        $name = $values['name'];
      }
      elseif (isset($_SERVER['HTTP_USER_AGENT'])) {
        $name = $this->getAgent();
      }
      $this->setTrusted($this->generateBrowserId(), $name);
    }
    return TRUE;
  }

  /**
   * Get list of trusted browsers.
   *
   * @return array
   *   List of current trusted browsers.
   */
  public function getTrustedBrowsers() {
    return $this->getUserData('tfa', 'tfa_trusted_browser', $this->uid, $this->userData);
  }

  /**
   * Delete a trusted browser by its ID.
   *
   * @param int $id
   *   ID of the browser to delete.
   *
   * @return bool
   *   TRUE if successful otherwise FALSE.
   */
  public function deleteTrustedId($id) {
    return $this->deleteTrusted($id);
  }

  /**
   * Delete all trusted browsers.
   *
   * @return bool
   *   TRUE if successful otherwise FALSE.
   */
  public function deleteTrustedBrowsers() {
    return $this->deleteTrusted();
  }



  /**
   * {@inheritdoc}
   */
  public function getOverview($params) {
    $trusted_browsers = array();
    foreach ($this->getTrustedBrowsers() as $device) {
      $date_formatter = \Drupal::service('date.formatter');
      $vars = array(
        '!set' => $date_formatter->format($device['created']),
        '@browser' => $device['name'],
      );
      if (empty($device['last_used'])) {
        $message = t('@browser, set !set', $vars);
      }
      else {
        $vars['!time'] = $date_formatter->format($device['last_used']);
        $message = t('@browser, set !set, last used !time', $vars);
      }
      $trusted_browsers[] = $message;
    }
    $output = array(
      'heading' => array(
        '#theme' => 'html_tag',
        '#tag' => 'h3',
        '#value' => t('Trusted browsers'),
      ),
      'description' => array(
        '#theme' => 'html_tag',
        '#tag' => 'p',
        '#value' => t('Browsers that will not require a verification code during login.'),
      ),
    );
    if (!empty($trusted_browsers)) {

      $output['list'] = array(
        '#theme' => 'item_list',
        '#items' => $trusted_browsers,
        '#title' => t('Browsers that will not require a verification code during login.'),
      );
    }
    $output['link'] = array(
      '#theme' => 'links',
      '#links' => array(
        'admin' => array(
          'title' => 'Configure Trusted Browsers',
          'url' => Url::fromRoute('tfa.validation.setup', [
            'user' => $params['account']->id(),
            'method' => $params['plugin_id'],
          ]),
        ),
      ),
    );

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function getHelpLinks() {
    return $this->pluginDefinition['help_links'];
  }

}