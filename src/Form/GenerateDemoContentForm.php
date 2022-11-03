<?php

namespace Drupal\social_demo_gpt3\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Site\Settings;

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
   * Constructs a new GenerateDemoContentForm object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('entity_type.manager')
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
      '#title' => t('Method'),
      '#options' => ['manual' => 'Manual', 'automatic' => 'Automatic'],
      '#default_value' => 'Manual',
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

    if ($method === 'manual') {
      $company_name = $form_state->getValue("company_name");
      $company_description = $form_state->getValue("company_description");
    }
    elseif ($method === 'automatic') {
      $company_url = $form_state->getValue("website_url");
      $response = $this->getOneAIData($company_url);
    }

    $users = $this->entityTypeManager->getStorage("user")->loadByProperties();

    // Post generation.
    $i = 0;
    $post_prompt = "Write a user post about company $company_name which is $company_description to be published on a social network";
    $api_key = Settings::get('openai_gpt3_api_key', '');
    if (!$api_key) {
      $this->messenger()->addMessage("No Open AI GPT3 api key found, please add it to your setting.php file.");
      return;
    }

    $gpt_posts = [];
    while ($i < 5) {
      $ai_response = $this->getGpt3Data($post_prompt);

      foreach ($ai_response->choices as $choice) {
        $gpt_posts[] = str_replace("\n", "", $choice->text);
      }

      $i++;
    }

    foreach ($gpt_posts as $post) {
      $this->entityTypeManager->getStorage("post")->create([
        "user_id" => $users[array_rand($users)]->id(),
        "status" => 1,
        "type" => "post",
        "langcode" => "en",
        "field_post" => $post,
        "field_visibility" => 2,
      ])->save();
    }

    $this->messenger()->addMessage("GPT3 successfully generated post content.");

    // Event generations.
    $i = 0;
    $event_title_prompt = "Create an event title about company $company_description or about company $company_name to be published on a social network";
    $gpt_events = [];

    // First create titles.
    while ($i < 5) {
      $ai_response = $this->getGpt3Data($event_title_prompt);

      foreach ($ai_response->choices as $choice) {
        $event_title = str_replace("\n", "", $choice->text);
        $gpt_events[$event_title] = "";
      }

      $i++;
    }

    foreach ($gpt_events as $title => $text) {
      $event_description_prompt = "Create event description for title $title to be published on a social network";
      $ai_response = $this->getGpt3Data($event_description_prompt);

      foreach ($ai_response->choices as $choice) {
        $event_description = str_replace("\n", "", $choice->text);
        $gpt_events[$title] = $event_description;
      }
    }

    // Create nodes.
    foreach ($gpt_events as $title => $description) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = $this->entityTypeManager->getStorage('node')
        ->create([
          "type" => "event",
          "title" => $title,
        ]);

      $node->set('body', $description);
      $node->body->format = 'full_html';

      $node->set('field_event_date', date('Y-m-d', strtotime("2030-10-10")));
      $node->set('field_event_date_end', date('Y-m-d', strtotime("2030-11-10")));

      $node->setOwnerId($users[array_rand($users)]->id());
      $node->setPublished();

      $node->save();
    }

    // Topic generations.
    $i = 0;
    $topic_title_prompt = "Create an topic title about company $company_description or about company $company_name to be published on a social network";
    $gpt_topics = [];

    // First create titles.
    while ($i < 5) {
      $ai_response = $this->getGpt3Data($topic_title_prompt);

      foreach ($ai_response->choices as $choice) {
        $topic_title = str_replace("\n", "", $choice->text);
        $gpt_topics[$topic_title] = "";
      }

      $i++;
    }

    foreach ($gpt_topics as $title => $text) {
      $topic_description_prompt = "Create topic description for title $title to be published on a social network";
      $ai_response = $this->getGpt3Data($topic_description_prompt);

      foreach ($ai_response->choices as $choice) {
        $topic_description = str_replace("\n", "", $choice->text);
        $gpt_topics[$title] = $topic_description;
      }
    }

    // Create nodes.
    foreach ($gpt_topics as $title => $description) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = $this->entityTypeManager->getStorage('node')
        ->create([
          "type" => "topic",
          "title" => $title,
        ]);

      $node->set('body', $description);
      $node->body->format = 'full_html';

      $node->setOwnerId($users[array_rand($users)]->id());
      $node->setPublished();

      $node->save();
    }

  }

  /**
   * Get AI data.
   */
  public function getGpt3Data(string $prompt) {
    $api_key = Settings::get('openai_gpt3_api_key', '');
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "{\n  \"model\": \"text-davinci-002\",\n  \"prompt\": \"" . $prompt . "n\",\n  \"temperature\": 1,\n  \"max_tokens\": 1301,\n  \"top_p\": 1,\n  \"frequency_penalty\": 0,\n  \"presence_penalty\": 0\n}");

    $headers = [];
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Authorization: Bearer ' . $api_key;

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
      throw new \Exception(curl_error($ch));
    }

    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_status != 200) {
      curl_close($ch);
      $error = curl_error($ch);
      $this->messenger()->addError("GPT3 returned an error: $error");
      return;
    }

    curl_close($ch);
    $response = json_decode($result);

    return $response;
  }

  /**
   * Get AI data.
   */
  public function getOneAIData(string $prompt) {
    $api_key = Settings::get('oneai_api_key', '');
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://api.oneai.com/api/v0/pipeline');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"input\":\"" . $prompt . "\",\"input_type\":\"article\",\"output_type\":\"json\",\"steps\":[{\"skill\":\"html-extract-article\"},{\"skill\":\"summarize\"},{\"skill\":\"article-topics\"},{\"skill\":\"keywords\"}]}");

    $headers = [];
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'api-key: ' . $api_key;

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
      throw new \Exception(curl_error($ch));
    }

    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_status != 200) {
      curl_close($ch);
      $error = curl_error($ch);
      $this->messenger()->addError("OneAI returned an error: $error");
      return;
    }

    curl_close($ch);
    $response = json_decode($result);

    return $response;
  }

}
