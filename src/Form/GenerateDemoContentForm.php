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

    $form["company_name"] = [
      "#type" => "textfield",
      "#title" => $this->t("Company name"),
      "#description" => $this->t("eq: Open Social"),
      "#required" => TRUE,
    ];

    $form["company_description"] = [
      "#type" => "textfield",
      "#title" => $this->t("Company description"),
      "#description" => $this->t("eq: The Community Engagement Platform"),
      "#required" => TRUE,
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
    $company_name = $form_state->getValue("company_name");
    $company_description = $form_state->getValue("company_description");

    $i = 0;
    $post_prompt = "Write a user post about company $company_name which is $company_description to be published on a social network";
    $api_key = Settings::get('openai_gpt3_api_key', '');
    if (!$api_key) {
      $this->messenger()->addMessage("No Open AI GPT3 api key found, please add it to your setting.php file.");
      return;
    }

    $gpt_posts = [];
    while ($i < 5) {
      // @todo Probally better to do it with Guzzle.
      $ch = curl_init();

      curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/completions');
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, "{\n  \"model\": \"text-davinci-002\",\n  \"prompt\": \"" . $post_prompt . "n\",\n  \"temperature\": 1,\n  \"max_tokens\": 1301,\n  \"top_p\": 1,\n  \"frequency_penalty\": 0,\n  \"presence_penalty\": 0\n}");

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

      foreach ($response->choices as $choice) {
        $gpt_posts[] = str_replace("\n", "", $choice->text);
      }

      $i++;
    }

    if (!$gpt_posts) {
      $this->messenger()->addMessage("GPT3 didn't return any results.");
      return;
    }

    $users = $this->entityTypeManager->getStorage("user")->loadByProperties();
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

    $this->messenger()->addMessage("GPT3 successfully generated content.");
  }

}
