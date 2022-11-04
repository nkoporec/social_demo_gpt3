<?php

namespace Drupal\social_demo_gpt3;

/**
 * Defines the GenerateGpt3Content batch service.
 */
class GenerateGpt3Content {

  /**
   * Generates posts.
   *
   * @param string $method
   *   Method type.
   * @param string $company_name
   *   Company name.
   * @param string $company_description
   *   Company description.
   * @param string $summary
   *   AI website summary.
   * @param array $users
   *   Users.
   * @param object $context
   *   Batch context.
   */
  public static function generatePostContent($method, $company_name, $company_description, $summary, array $users, &$context) {
    /** @var \Drupal\social_demo_gpt3\Gpt3Client $gpt3_client */
    $gpt3_client = \Drupal::service('social_demo_gpt3.client');

    if ($method === 'manual') {
      $prompt = "Write a user post about company $company_name which and $company_description to be published on a social network";
    }
    elseif ($method === 'automatic') {
      $prompt = "Write a user post about $summary to be published on a social network";
    }

    $ai_response = $gpt3_client->getGpt3Data($prompt);
    $gpt_posts = [];

    foreach ($ai_response->choices as $choice) {
      $gpt_posts[] = str_replace("\n", "", $choice->text);
    }

    foreach ($gpt_posts as $post_text) {
      $post = \Drupal::entityTypeManager()->getStorage("post")->create([
        "user_id" => $users[array_rand($users)]->id(),
        "status" => 1,
        "type" => "photo",
        "langcode" => "en",
        "field_post" => $post_text,
        "field_visibility" => 2,
      ]);

      if ($method === 'manual') {
        $image = $gpt3_client->getGpt3Image("A random image to be published on social network");
      }
      elseif ($method === 'automatic') {
        $image = $gpt3_client->getGpt3Image("An image about topic $summary to be published on social network");
      }

      if ($image) {
        $post->set("field_post_image", ["target_id" => $image->id()]);
      }

      $post->save();

      // Randomly seed comments.
      if (mt_rand(0, 1)) {
        $i = 0;
        $number_of_comments = rand(1, 3);
        while ($i < $number_of_comments) {
          $comment_prompt = "Write a comment about how great a post on this social network was.";
          $ai_response = $gpt3_client->getGpt3Data($comment_prompt);
          foreach ($ai_response->choices as $choice) {
            $comment_body = str_replace("\n", "", $choice->text);
            $comment = \Drupal::entityTypeManager()->getStorage("comment")->create([
              'entity_type' => 'post',
              'entity_id'   => $post->id(),
              'field_name'  => 'field_post_comments',
              'uid' => $users[array_rand($users)]->id(),
              'comment_type' => 'post_comment',
              'subject' => 'wow',
              'status' => 1,
            ]);

            $comment->set('field_comment_body', $comment_body);
            $comment->field_comment_body->format = 'full_html';
            $comment->save();
          }

          $i++;
        }
      }

    }
  }

  /**
   * Generates nodes.
   *
   * @param string $type
   *   Node type.
   * @param string $method
   *   Method name.
   * @param string $summary
   *   AI website summary.
   * @param array $users
   *   Users.
   * @param string $company_name
   *   Company name.
   * @param string $company_description
   *   Company description.
   * @param object $context
   *   Batch context.
   */
  public static function generateNodeContent($type, $method, $summary, array $users, $company_name, $company_description, &$context) {
    /** @var \Drupal\social_demo_gpt3\Gpt3Client $gpt3_client */
    $gpt3_client = \Drupal::service('social_demo_gpt3.client');

    if ($method === 'manual') {
      $prompt = "Create an $type title about $company_name and $company_description to be published on a social network";
    }
    elseif ($method === 'automatic') {
      $prompt = "Create an $type title about $summary to be published on a social network";
    }

    $ai_response = $gpt3_client->getGpt3Data($prompt);

    $gpt_data = [];

    foreach ($ai_response->choices as $choice) {
      $title = str_replace("\n", "", $choice->text);
      $gpt_data[$title] = "";
    }

    foreach ($gpt_data as $title => $text) {
      $description_prompt = "Create $type description for title $title to be published on a social network";
      $ai_response = $gpt3_client->getGpt3Data($description_prompt);

      foreach ($ai_response->choices as $choice) {
        $description = str_replace("\n", "", $choice->text);
        $gpt_data[$title] = $description;
      }
    }

    // Create nodes.
    foreach ($gpt_data as $title => $description) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')
        ->create([
          "type" => $type,
          "title" => $title,
        ]);

      $node->set('body', $description);
      $node->body->format = 'full_html';

      if ($type == "event") {
        $node->set('field_event_date', date('Y-m-d', strtotime("2030-10-10")));
        $node->set('field_event_date_end', date('Y-m-d', strtotime("2030-11-10")));
      }

      $image = $gpt3_client->getGpt3Image("An image about $type with title $title to be published on social network");
      if ($image) {
        $node->set("field_" . $type . "_image", ["target_id" => $image->id()]);
      }

      $node->setOwnerId($users[array_rand($users)]->id());
      $node->setPublished();

      $node->save();
    }
  }

  /**
   * Batch finish callback.
   *
   * @param bool $success
   *   If batch was successfull.
   * @param array $results
   *   Number of results.
   * @param array $operations
   *   Operations performed.
   */
  public static function finishedCallback($success, array $results, array $operations) {
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
        count($results),
        'One item processed.', '@count items processed.'
      );
    }
    else {
      $message = t('Finished with an error.');
    }

    \Drupal::messenger()->addMessage($message);
  }

}
