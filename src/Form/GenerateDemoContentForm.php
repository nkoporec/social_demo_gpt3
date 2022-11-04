<?php

namespace Drupal\social_demo_gpt3\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Site\Settings;
use Drupal\social_demo_gpt3\Gpt3Client;

/**
 * Generates demo content based on GPT3.
 *
 * @internal
 */
class GenerateDemoContentForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The GPT3 client.
   *
   * @var \Drupal\social_demo_gpt3\Gpt3Client
   */
  protected Gpt3Client $gpt3Client;

  /**
   * Constructs a new GenerateDemoContentForm object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Gpt3Client $gpt3_client) {
    $this->entityTypeManager = $entity_type_manager;
    $this->gpt3Client = $gpt3_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('social_demo_gpt3.client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'social_demo_gpt3_generate_demo_content';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $default_ip
   *   (optional) IP address to be passed on to
   *   \Drupal::formBuilder()->getForm() for use as the default value of the IP
   *   address form field.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $default_ip = '') {
    $form = [];

    $form['method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Method'),
      '#options' => [
        'manual' => $this->t('Manual'),
        'automatic' => $this->t('Automatic'),
      ],
      '#default_value' => 'manual',
      '#required' => TRUE,
    ];

    $form["company_name"] = [
      "#type" => "textfield",
      "#title" => $this->t("Company name"),
      "#description" => $this->t("eq: Open Social"),
      '#states' => [
        'visible' => [
          ':input[name="method"]' => ['value' => 'manual'],
        ],
        'required' => [
          ':input[name="method"]' => ['value' => 'manual'],
        ],
      ],
    ];

    $form["company_description"] = [
      "#type" => "textfield",
      "#title" => $this->t("Company description"),
      "#description" => $this->t("eq: The Community Engagement Platform"),
      '#states' => [
        'visible' => [
          ':input[name="method"]' => ['value' => 'manual'],
        ],
        'required' => [
          ':input[name="method"]' => ['value' => 'manual'],
        ],
      ],
    ];

    $form["website_url"] = [
      "#type" => "url",
      "#title" => $this->t("Company page"),
      "#description" => $this->t("The page you want to crawl for information for example https://www.getopensocial.com"),
      '#states' => [
        'visible' => [
          ':input[name="method"]' => ['value' => 'automatic'],
        ],
        'required' => [
          ':input[name="method"]' => ['value' => 'automatic'],
        ],
      ],
    ];

    $form["number_of_items"] = [
      "#type" => "number",
      "#title" => $this->t("Number of items"),
      "#description" => $this->t("How much content should we generate for each type."),
      "#default_value" => 3,
    ];

    $form["submit"] = [
      "#type" => "submit",
      "#value" => $this->t("Save"),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $method = $form_state->getValue("method");
    $number_of_items = $form_state->getValue("number_of_items");

    if ($method === 'manual') {
      $company_name = $form_state->getValue("company_name");
      $company_description = $form_state->getValue("company_description");
    }
    elseif ($method === 'automatic') {
      $company_url = $form_state->getValue("website_url");
      $response = $this->gpt3Client->getOneAiData($company_url);
      $summary = $response->output[1]->contents[0]->utterance;
    }

    $users = $this->entityTypeManager->getStorage("user")->loadByProperties();

    $batch = [
      'title' => $this->t('Creating AI content ...'),
      'operations' => [],
      'init_message'     => $this->t('Booting up the robots ...'),
      'progress_message' => $this->t('Processed @current out of @total.'),
      'error_message'    => $this->t('An error occurred during processing'),
      'finished' => '\Drupal\social_demo_gpt3\GenerateContentService::finishedCallback',
    ];

    $api_key = Settings::get('openai_gpt3_api_key', '');
    if (!$api_key) {
      $this->messenger()->addMessage("No Open AI GPT3 api key found, please add it to your setting.php file.");
      return;
    }

    // Post generation.
    $i = 0;
    while ($i < $number_of_items) {
      $batch['operations'][] = [
        '\Drupal\social_demo_gpt3\GenerateGpt3Content::generatePostContent',
        [
          $method,
          $company_name,
          $company_description,
          $summary,
          $users,
        ],
      ];

      $i++;
    }

    // Event generations.
    $i = 0;
    while ($i < $number_of_items) {
      $batch['operations'][] = [
        '\Drupal\social_demo_gpt3\GenerateGpt3Content::generateNodeContent',
        [
          "event",
          $method,
          $summary,
          $users,
          $company_name,
          $company_description,
        ],
      ];

      $i++;
    }

    // Topic generations.
    $i = 0;
    while ($i < $number_of_items) {
      $batch['operations'][] = [
        '\Drupal\social_demo_gpt3\GenerateGpt3Content::generateNodeContent',
        [
          "topic",
          $method,
          $summary,
          $users,
          $company_name,
          $company_description,
        ],
      ];

      $i++;
    }

    batch_set($batch);
  }

}
