<?php

namespace Drupal\shp_share\Form;

use Drupal\Core\Url;
use BaconQrCode\Writer;
use Drupal\Core\Form\FormBase;
use BaconQrCode\Renderer\ImageRenderer;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;

/**
 * Class GroupMembersForm.
 */
class GroupMembersForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'shp_share_group_members_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $bag = \Drupal::routeMatch()->getParameter('node');
    if (empty($bag)) {
      return $form;
    }

    $form['members_group_grid'] = [
      '#type' => 'view',
      '#name' => 'group_members_grid',
      '#display_id' => 'grid',
    ];

    /** @var \Drupal\group\Entity\GroupInterface[] $groups */
    $groups = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties([
        'type' => 'bag_members',
        'field_bag' => $bag->id(),
        'status' => 1,
      ]);

    $group = reset($groups);
    if (!empty($group)) {
      $members = $group->getMembers();

      $users = [];
      foreach ($members as $member) {
        $users[] = $member->getUser()->id();
      }

      if (!empty($users)) {
        $arguments = implode('+', $users);
        $form['members_group_grid']['#arguments'] = [$arguments];
      }
    }

    /** @var \Drupal\anonymous_token\Access\AnonymousCsrfTokenGenerator $csrf_token */
    $csrf_token = \Drupal::service('anonymous_token.csrf_token');
    $invitation_link = Url::fromRoute('shp_share.group.link_invitation', [
        'bag' => $bag->id(),
      ], [
        'query' => [
          'token' => $csrf_token->get(),
        ],
        'absolute' => TRUE
      ],
    )->toString();

    $form['parent'] = [
      '#type' => 'details',
      '#title' => $this->t('Invite members'),
    ];

    $form['parent']['link'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Share bag using link:'),
      '#description' => $this->t('Copy link to share access to the bag.'),
      '#default_value' => $invitation_link,
      '#size' => 20,
      '#attributes' => [
        'readonly' => 'readonly',
      ],
    ];

    $directory = 'public://barcode';
    $file_name = "qr-code-bag-{$bag->id()}.png";
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $file_system->prepareDirectory($directory, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    $file_uri = $directory . '/' . $file_name;
    $real_path = $file_system->realpath($file_uri);
    // If file doesn't exist, create it.
    if (!$real_path) {
      $file = \Drupal::service('file.repository')->writeData('', $directory . '/' . $file_name);
      $file_uri = $file->getFileUri();
      $real_path = $file_system->realpath($file_uri);
    }

    $renderer = new ImageRenderer(
      new RendererStyle(400),
      new ImagickImageBackEnd()
    );
    $writer = new Writer($renderer);
    $writer->writeFile($invitation_link, $real_path);

    // The image.factory service will check if our image is valid.
    $image = \Drupal::service('image.factory')->get($file_uri);

    if (!$image->isValid()) {
      return $form;
    }

    $form['parent']['barcode'] = [
      '#theme' => 'image_style',
      '#width' => $image->getWidth() ?? NULL,
      '#height' => $image->getHeight() ?? NULL,
      '#style_name' => 'medium',
      '#uri' => $file_uri,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
