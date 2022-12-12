<?php

namespace Drupal\shp_share\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class InvitationController.
 *
 * @package Drupal\shp_share\Controller
 */
class InvitationController extends ControllerBase {

  /**
   * Check access to the invitation controller.
   *
   * @param \Drupal\node\NodeInterface $bag
   *   The bag node.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(NodeInterface $bag) {
    /** @var \Drupal\anonymous_token\Access\AnonymousCsrfTokenGenerator $csrf_token */
    $csrf_token = \Drupal::service('anonymous_token.csrf_token');
    $query = \Drupal::request()->query->all();
    // @TODO Validate the token.
    return AccessResult::allowedif(isset($query['token']));
  }

  /**
   * Share a node with a user.
   *
   * @throws \Exception
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function share(NodeInterface $bag): RedirectResponse {
    /** @var \Drupal\group\Entity\GroupInterface[] $groups */
    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties([
        'type' => 'bag_members',
        'field_bag' => $bag->id(),
        'status' => 1,
      ]);

    $group = reset($groups);
    $current_account = $this->currentUser();
    /** @var \Drupal\user\UserInterface $current_user */
    $current_user = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->load($current_account->id());

    if (!$current_account->isAnonymous()) {
      $group->addMember($current_user);
      $group->save();

      return $this->redirect('entity.node.canonical', [
        'node' => $bag->id()
      ]);
    }
    else {
      $current_user = $this->createUserOnTheFly();
      $group->addMember($current_user);
      $group->save();

      $timestamp = REQUEST_TIME;
      $langcode = $options['langcode'] ?? $current_user->getPreferredLangcode();
      $url = Url::fromRoute('user.reset', [
        'uid' => $current_user->id(),
        'timestamp' => $timestamp,
        'hash' => user_pass_rehash($current_user, $timestamp),
      ], [
        'absolute' => TRUE,
        'language' => \Drupal::languageManager()->getLanguage($langcode),
        'destination' => Url::fromRoute('entity.node.canonical', [
          'node' => $bag->id()
        ])->toString(),
      ])->toString();

      return new RedirectResponse($url);
    }
  }

  /**
   * Creates a user on the fly.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createUserOnTheFly():UserInterface {
    $email = $this->generateEmailAddress(32, 16);
    $password = \Drupal::service('password_generator')->generate();

    // If the user account doesn't exist, need to create it.
    /** @var \Drupal\user\UserInterface $new_user */
    $new_user = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->create([
        'status' => 1,
        'init' => $email,
        'mail' => $email,
        'password' => $password,
        'name' => 'email_registration_' . $password,
        'legal_accept' => TRUE,
      ]);

    $new_user->save();
    return $new_user;
  }

  /**
   * Generate random temporal email.
   *
   * @param $maxLenLocal
   *   The maximum length of the local part.
   * @param $maxLenDomain
   *   The maximum length of the domain part.
   *
   * @return string
   *   The generated email.
   */
  public function generateEmailAddress($maxLenLocal=64, $maxLenDomain=255): string {
    $numeric        =  '0123456789';
    $alphabetic     = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $extras         = '.';
    $all            = $numeric . $alphabetic . $extras;
    $alphaNumeric   = $alphabetic . $numeric;
    $randomString   = '';

    // GENERATE 1ST 4 CHARACTERS OF THE LOCAL-PART
    for ($i = 0; $i < 4; $i++) {
      $randomString .= $alphabetic[rand(0, strlen($alphabetic) - 1)];
    }

    // GENERATE A NUMBER BETWEEN 20 & 60
    $rndNum = rand(20, $maxLenLocal-4);

    for ($i = 0; $i < $rndNum; $i++) {
      $randomString .= $all[rand(0, strlen($all) - 1)];
    }

    // ADD AN @ SYMBOL...
    $randomString .= "@";

    // GENERATE DOMAIN NAME - INITIAL 3 CHARS:
    for ($i = 0; $i < 3; $i++) {
      $randomString .= $alphabetic[rand(0, strlen($alphabetic) - 1)];
    }

    // GENERATE A NUMBER BETWEEN 15 & $maxLenDomain-7
    $rndNum2        = rand(15, $maxLenDomain-7);
    for ($i = 0; $i < $rndNum2; $i++) {
      $randomString .= $all[rand(0, strlen($all) - 1)];
    }

    // ADD AN DOT . SYMBOL...
    $randomString .= ".";

    // GENERATE TLD: 4
    for ($i = 0; $i < 4; $i++) {
      $randomString .= $alphaNumeric[rand(0, strlen($alphaNumeric) - 1)];
    }

    return $randomString;
  }

}
