<?php

namespace Drupal\social_demo_gpt3;

use Drupal\Core\Site\Settings;

/**
 * Defines the Gpt3Client service.
 */
class Gpt3Client {

  /**
   * Get AI text data.
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
      \Drupal::messenger()->addError("GPT3 returned an error: $error");
      return NULL;
    }

    curl_close($ch);
    $response = json_decode($result);

    return $response;
  }

  /**
   * Get AI image.
   */
  public function getGpt3Image(string $prompt) {
    $api_key = Settings::get('openai_gpt3_api_key', '');
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/images/generations');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "{\n    \"prompt\": \"" . $prompt . "\",\n    \"n\": 1,\n    \"size\": \"1024x1024\"\n  }");

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
      \Drupal::messenger()->addError("GPT3 returned an error: $error");
      return NULL;
    }

    curl_close($ch);
    $response = json_decode($result);

    $dall_e_image = end($response->data);
    $dall_e_image_url = $dall_e_image->url;

    /** @var \Drupal\file\FileInterface $local_img */
    $local_img = system_retrieve_file($dall_e_image_url, "public://", TRUE);

    return $local_img;
  }

  /**
   * Get AI data.
   */
  public function getOneAiData(string $prompt) {
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
      \Drupal::messenger()->addError("OneAI returned an error: $error");
      return;
    }

    curl_close($ch);
    $response = json_decode($result);

    return $response;
  }

}
