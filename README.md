## social_demo_gpt3

A Drupal module to be used with Open Social to generate demo content based on a company description, its using the OpenAI GPT3 engine to generate the content.

### Getting started

 1. Go to https://beta.openai.com/ and retrieve an API key
 2. Add `$settings['openai_gpt3_api_key'] = 'YOUR_API_KEY'` to settings.php
 3. Enable the module
 4. Go to `/admin/config/development/social-demo-gpt3/generate` and generate the content.

If you want to include text-analysis on urls

1. Go to https://www.oneai.com/ and retrieve an API key
2. Add `$settings['oneai_api_key'] = 'YOUR_API_KEY'` to settings.php
3. Choose automatic during content generation.
