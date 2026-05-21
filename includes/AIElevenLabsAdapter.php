<?php

/**
 * @file
 * ElevenLabs adapter for the AI module.
 */

class AIElevenLabsAdapter extends AIAdapterBase {

  /**
   * ElevenLabs model IDs that are deprecated and should be hidden from lists.
   *
   * @var string[]
   */
  protected $deprecatedModels = [
    'eleven_monolingual_v1',
    'eleven_multilingual_v1',
  ];

  /**
   * Known generic shared voice names that should not be sent to ElevenLabs.
   *
   * @var string[]
   */
  protected $genericVoices = [
    'alloy',
    'echo',
    'fable',
    'onyx',
    'nova',
    'shimmer',
  ];

  /**
   * Constructor.
   */
  public function __construct($api_key, ?AIApi $api = NULL) {
    parent::__construct($api_key, $api);
  }

  /**
   * ElevenLabs API base URL.
   *
   * @var string
   */
  protected $baseUrl = 'https://api.elevenlabs.io/v1';

  /**
   * {@inheritdoc}
   */
  protected function getDefaultHeaders(): array {
    return [
      'xi-api-key' => $this->apiKey,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getModels(): array {
    $models = [];

    foreach ($this->getModelData() as $model) {
      $id = !empty($model['model_id']) ? (string) $model['model_id'] : '';
      if ($id === '' || in_array($id, $this->deprecatedModels, TRUE)) {
        continue;
      }
      $models[$id] = !empty($model['name']) ? (string) $model['name'] : $id;
    }

    foreach ($this->getSpeechToTextModels() as $id => $label) {
      if (!isset($models[$id])) {
        $models[$id] = $label;
      }
    }

    asort($models);
    return $models;
  }

  /**
   * Get models by capability.
   */
  public function getModelsByCapability($capability): array {
    $capability = strtolower((string) $capability);
    $filtered = [];
    $provider_id = 'elevenlabs';

    switch ($capability) {
      case 'tts':
      case 'text_to_speech':
        foreach ($this->getModelData() as $model) {
          if (empty($model['can_do_text_to_speech'])) {
            continue;
          }
          $id = !empty($model['model_id']) ? (string) $model['model_id'] : '';
          if ($id === '' || in_array($id, $this->deprecatedModels, TRUE)) {
            continue;
          }
          $filtered[$id] = !empty($model['name']) ? (string) $model['name'] : $id;
        }
        break;

      case 'stt':
      case 'speech_to_text':
      case 'audio':
        $filtered = $this->getSpeechToTextModels();
        break;

      case 'speech_to_speech':
        foreach ($this->getModelData() as $model) {
          if (empty($model['can_do_voice_conversion'])) {
            continue;
          }
          $id = !empty($model['model_id']) ? (string) $model['model_id'] : '';
          if ($id === '') {
            continue;
          }
          $filtered[$id] = !empty($model['name']) ? (string) $model['name'] : $id;
        }
        break;
    }

    backdrop_alter('ai_model_capabilities', $filtered, $capability, $provider_id);
    asort($filtered);
    return $filtered;
  }

  /**
   * Avoid listing ElevenLabs in chat selectors.
   */
  public function getChatModels(): array {
    return [];
  }

  /**
   * ElevenLabs does not support image generation here.
   */
  public function getImageModels(): array {
    return [];
  }

  /**
   * ElevenLabs does not support vision here.
   */
  public function getVisionModels(): array {
    return [];
  }

  /**
   * ElevenLabs does not support embeddings here.
   */
  public function getEmbeddingModels(): array {
    return [];
  }

  /**
   * ElevenLabs does not support moderation here.
   */
  public function getModerationModels(): array {
    return [];
  }

  /**
   * Get speech-to-text models.
   */
  public function getSpeechToTextModels(): array {
    return [
      'scribe_v2' => 'Scribe v2',
      'scribe_v1' => 'Scribe v1',
    ];
  }

  /**
   * Get available voices.
   *
   * @param string|null $model
   *   Optional model ID to filter compatible voices.
   *
   * @return array
   *   Array of voice_id => label.
   */
  public function getVoices(?string $model = NULL): array {
    $voices = [];
    $data = $this->requestJson('/voices');

    if (empty($data['voices']) || !is_array($data['voices'])) {
      return $voices;
    }

    foreach ($data['voices'] as $voice) {
      $voice_id = !empty($voice['voice_id']) ? (string) $voice['voice_id'] : '';
      if ($voice_id === '') {
        continue;
      }

      if (!empty($model) && !empty($voice['high_quality_base_model_ids']) && is_array($voice['high_quality_base_model_ids'])) {
        if (!in_array($model, $voice['high_quality_base_model_ids'], TRUE)) {
          continue;
        }
      }

      $voices[$voice_id] = $this->formatVoiceLabel($voice, $voice_id);
    }

    asort($voices);
    return $voices;
  }

  /**
   * Build richer, human-readable label for a voice option.
   */
  protected function formatVoiceLabel(array $voice, string $voice_id): string {
    $label = !empty($voice['name']) ? (string) $voice['name'] : $voice_id;
    $parts = [];

    if (!empty($voice['labels']) && is_array($voice['labels'])) {
      $labels = $voice['labels'];

      if (!empty($labels['descriptive'])) {
        $parts[] = (string) $labels['descriptive'];
      }

      $traits = [];
      foreach (['use_case', 'gender', 'accent', 'age'] as $key) {
        if (!empty($labels[$key])) {
          $traits[] = (string) $labels[$key];
        }
      }
      if (!empty($traits)) {
        $parts[] = implode(', ', $traits);
      }
    }
    elseif (!empty($voice['category'])) {
      $parts[] = (string) $voice['category'];
    }

    if (empty($parts)) {
      return $label;
    }

    return $label . ' - ' . implode(' | ', $parts);
  }

  /**
   * {@inheritdoc}
   */
  public function completions(string $model, string $prompt, $temperature, $max_tokens = 512, bool $stream_response = FALSE) {
    watchdog('ai_provider_elevenlabs', 'Completions are not supported by ElevenLabs.', [], WATCHDOG_WARNING);
    throw new \RuntimeException('Completions are not supported by ElevenLabs.');
  }

  /**
   * {@inheritdoc}
   */
  public function chat(string $model, array $messages, $temperature, $max_tokens = 1024, bool $stream_response = FALSE, array $context_extra = []) {
    watchdog('ai_provider_elevenlabs', 'Chat is not supported by ElevenLabs.', [], WATCHDOG_WARNING);
    throw new \RuntimeException('Chat is not supported by ElevenLabs.');
  }

  /**
   * {@inheritdoc}
   */
  public function images(string $model, string $prompt, string $size, string $response_format, string $quality = 'standard', string $style = 'natural', ?string $output_format = NULL) {
    throw new \RuntimeException('Image generation is not supported by ElevenLabs.');
  }

  /**
   * {@inheritdoc}
   */
  public function textToSpeech(string $model, string $input, string $voice, string $response_format) {
    $voice_id = $this->resolveVoiceId($voice, $model);
    if ($voice_id === '') {
      throw new \Exception('An ElevenLabs voice ID is required for text-to-speech.');
    }

    $payload = [
      'text' => $input,
      'model_id' => $model,
    ];

    $query = [
      'output_format' => $this->mapOutputFormat($response_format),
    ];

    return $this->requestBinary('/text-to-speech/' . rawurlencode($voice_id), $payload, $query);
  }

  /**
   * {@inheritdoc}
   */
  public function speechToText(string $model, string $file, string $task = 'transcribe', $temperature = 0.4, string $response_format = 'verbose_json') {
    if (!in_array($task, ['transcribe', 'translate'], TRUE)) {
      throw new \InvalidArgumentException('Task must be transcribe or translate.');
    }

    if ($task === 'translate') {
      throw new \InvalidArgumentException('ElevenLabs speech-to-text currently supports transcription only.');
    }

    if (!is_file($file) || !is_readable($file)) {
      throw new \InvalidArgumentException('Audio file is missing or unreadable: ' . $file);
    }

    $response = $this->requestMultipart('/speech-to-text', [
      'model_id' => $model ?: 'scribe_v2',
      'file'     => ['path' => $file],
    ]);

    if (!empty($response['text'])) {
      return $response['text'];
    }
    if (!empty($response['transcript'])) {
      return $response['transcript'];
    }
    throw new \RuntimeException('Speech-to-text response did not include transcript text.');
  }

  /**
   * {@inheritdoc}
   */
  public function moderation(string $input, string $model = 'omni-moderation-latest'): array {
    throw new \RuntimeException('Moderation is not supported by ElevenLabs.');
  }

  /**
   * {@inheritdoc}
   */
  public function embedding(string $input, string $model, bool $log = TRUE): array {
    throw new \RuntimeException('Embeddings are not supported by ElevenLabs.');
  }

  /**
   * Fetch model metadata from ElevenLabs.
   */
  protected function getModelData(): array {
    $cache = cache_get('ai_provider_elevenlabs_models');
    if (!empty($cache->data) && is_array($cache->data)) {
      return $cache->data;
    }

    $data = $this->requestJson('/models');
    $models = is_array($data) ? $data : [];
    cache_set('ai_provider_elevenlabs_models', $models, 'cache', REQUEST_TIME + 3600);
    return $models;
  }

  /**
   * Resolve the ElevenLabs voice ID from the caller input.
   */
  protected function resolveVoiceId(string $voice, string $model = ''): string {
    $voice = trim($voice);

    if (strpos($voice, ' :: ') !== FALSE) {
      $parts = explode(' :: ', $voice, 2);
      $voice = trim($parts[1]);
    }

    if ($voice !== '' && !in_array(strtolower($voice), $this->genericVoices, TRUE)) {
      return $voice;
    }

    $providers_root = config_get('ai.settings', 'providers') ?: [];
    if (!empty($providers_root['elevenlabs']['elevenlabs_default_voice_id'])) {
      return trim((string) $providers_root['elevenlabs']['elevenlabs_default_voice_id']);
    }

    $voices = $this->getVoices($model ?: NULL);
    if (!empty($voices)) {
      reset($voices);
      return (string) key($voices);
    }

    return '';
  }

  /**
   * Map generic audio formats to ElevenLabs output formats.
   */
  protected function mapOutputFormat(string $response_format): string {
    switch (strtolower($response_format)) {
      case 'opus':
        return 'opus_48000_64';

      case 'mp3':
      case 'aac':
      case 'flac':
      default:
        return 'mp3_44100_128';
    }
  }

  /**
   * Perform a JSON request.
   */
  protected function requestJson(string $endpoint, array $payload = [], array $query = [], string $method = 'GET'): array {
    $response = $this->request($endpoint, $method, $payload, $query, TRUE);
    return is_array($response) ? $response : [];
  }

  /**
   * Perform a binary request.
   */
  protected function requestBinary(string $endpoint, array $payload = [], array $query = []): string {
    $response = $this->request($endpoint, 'POST', $payload, $query, FALSE);
    return is_string($response) ? $response : '';
  }

  /**
   * Perform a multipart request via AIAdapterBase::makeMultipartRequest().
   */
  protected function requestMultipart(string $endpoint, array $fields = []): array {
    $url = $this->buildUrl($endpoint, []);
    return $this->makeMultipartRequest($url, $fields, 120);
  }

  /**
   * Execute a standard ElevenLabs API request via backdrop_http_request.
   */
  protected function request(string $endpoint, string $method = 'GET', array $payload = [], array $query = [], bool $decode_json = TRUE) {
    $url = $this->buildUrl($endpoint, $query);
    $options = [
      'method' => strtoupper($method),
      'headers' => array_merge(
        ['Accept' => $decode_json ? 'application/json' : '*/*'],
        $this->getDefaultHeaders()
      ),
      'timeout' => 120,
    ];

    if ($options['method'] !== 'GET') {
      $options['headers']['Content-Type'] = 'application/json';
      $options['data'] = json_encode($payload);
    }

    $response = backdrop_http_request($url, $options);

    if (!isset($response->code)) {
      throw new \Exception('ElevenLabs request failed: No response code.');
    }

    $http_code = (int) $response->code;
    $body = $response->data ?? '';

    if ($http_code < 200 || $http_code >= 300) {
      $message = $this->extractErrorMessage($body);
      throw new \Exception($this->buildErrorMessage($http_code, $message));
    }

    if (!$decode_json) {
      return $body;
    }

    $data = json_decode($body, TRUE);
    return is_array($data) ? $data : [];
  }

  /**
   * Build a full request URL.
   */
  protected function buildUrl(string $endpoint, array $query = []): string {
    $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
    if (!empty($query)) {
      $url .= '?' . http_build_query($query);
    }
    return $url;
  }

  /**
   * Build a human-readable exception message for known HTTP error codes.
   */
  protected function buildErrorMessage(int $http_code, string $api_message): string {
    $normalized_message = strtolower($api_message);
    $is_quota_error = strpos($normalized_message, 'quota') !== FALSE
      || strpos($normalized_message, 'credits remaining') !== FALSE
      || strpos($normalized_message, 'exceeds your quota') !== FALSE;

    switch ($http_code) {
      case 401:
        if ($is_quota_error) {
          return 'ElevenLabs: Insufficient credits/quota for this request. Reduce input length, add credits, or choose a cheaper model/voice. Details: ' . $api_message;
        }
        return 'ElevenLabs: Invalid or missing API key, or the requested model is no longer available on your plan. Check your API key and select a current model (e.g. eleven_multilingual_v2). Details: ' . $api_message;

      case 402:
        if ($is_quota_error) {
          return 'ElevenLabs: Insufficient credits/quota for this request. Reduce input length, add credits, or choose a cheaper model/voice. Details: ' . $api_message;
        }
        return 'ElevenLabs: Your plan does not support this voice or feature. Free accounts can only use premade voices (e.g. Rachel — 21m00Tcm4TlvDq8ikWAM, Adam — pNInz6obpgDQGcFmaJgB). Update the Default Voice ID in your ElevenLabs provider settings. Details: ' . $api_message;

      case 403:
        return 'ElevenLabs: Access denied. Your account does not have permission to use this resource. Details: ' . $api_message;

      case 422:
        if ($is_quota_error) {
          return 'ElevenLabs: Insufficient credits/quota for this request. Reduce input length, add credits, or choose a cheaper model/voice. Details: ' . $api_message;
        }
        return 'ElevenLabs: Invalid request parameters. Details: ' . $api_message;

      case 429:
        if ($is_quota_error) {
          return 'ElevenLabs: Insufficient credits/quota for this request. Reduce input length, add credits, or choose a cheaper model/voice. Details: ' . $api_message;
        }
        return 'ElevenLabs: Rate limit exceeded. Please wait before retrying. Details: ' . $api_message;

      default:
        return 'ElevenLabs request failed (HTTP ' . $http_code . '): ' . $api_message;
    }
  }

  /**
   * Extract a readable error message from an API response.
   */
  protected function extractErrorMessage($body): string {
    $data = json_decode((string) $body, TRUE);
    if (!empty($data['detail']['message'])) {
      return $data['detail']['message'];
    }
    if (!empty($data['detail'])) {
      return is_string($data['detail']) ? $data['detail'] : json_encode($data['detail']);
    }
    if (!empty($data['message'])) {
      return $data['message'];
    }
    return substr(trim((string) $body), 0, 300);
  }

  /**
   * {@inheritdoc}
   */
  public function chatWithTools(string $model, array $messages, array $tools, $temperature, $max_tokens = 1024, string $tool_choice = 'auto', array $context_extra = []): array {
    throw new \RuntimeException('Tool calling is not supported by the ElevenLabs adapter.');
  }
}
