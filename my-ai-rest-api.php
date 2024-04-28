<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Hooks into 'rest_api_init' to add new REST API endpoints
add_action( 'rest_api_init', 'my_ai_register_rest_apis' );

/**
 * Function to register new REST API endpoints
 * 
 * @return void
 */
function my_ai_register_rest_apis() {
    // Create REST API route to geneterate AI content
    register_rest_route('my-ai-content-generator/v1', '/contents', [
        'methods' => 'POST',
        'callback' => function($request) {
            $bedrock_runtime = my_ai_bedrock_runtime_client();
            $content = my_ai_generate_content($bedrock_runtime, $_POST);

            return $content;
        },
        'permission_callback' => function() {
            if (! current_user_can('edit_posts')) {
                return new WP_Error('rest_forbidden', esc_html__('You do not have permission to edit posts.'), [
                    'status' => rest_authorization_required_code(),
                ]);
            }
    
            return true;
        },
    ]);
}

/**
 * Function to build parameter body which sent to Amazon Bedrock
 * 
 * @param string $model_id
 * @param array $params
 * @return array
 */
function my_ai_build_bedrock_body($model_id, $params) {
    $param_body = [];
    switch (true) {
        // Amazon Titan parameters
        case strpos($model_id, 'amazon.titan') === 0:
            $param_body = [
                'inputText' => $params['prompt'],
                'textGenerationConfig' => [
                    'maxTokenCount' => $params['max_tokens'] ? $params['max_tokens'] : 4096,
                    'temperature' => $params['temperature'] ? $params['temperature'] : 0.8,
                    'topP' => $params['top_p'] ? $params['top_p'] : 0.9,
                    'stopSequences' => []
                ]
            ];
            break;
        
        // AI21 labs Jurassic parameters
        case strpos($model_id, 'ai21.j2') === 0:
            $param_body = [
                'prompt' => $params['prompt'],
                'maxTokens' => $params['max_tokens'] ? $params['max_tokens'] : 4096,
                'temperature' => $params['temperature'] ? $params['temperature'] : 0.8,
                'topP' => $params['top_p'] ? $params['top_p'] : 0.9,
                'stopSequences' => [],
                'countPenalty' => [
                    'scale' => 0
                ],
                'presencePenalty' => [
                    'scale' => 0
                ],
                'frequencyPenalty' => [
                    'scale' => 0
                ],
            ];
            break;

        // Anthropic Claude parameters
        case strpos($model_id, 'anthropic.claude') === 0:
            $param_body = [
                'anthropic_version' => 'bedrock-2023-05-31',
                'max_tokens' => $params['max_tokens'] ? $params['max_tokens'] : 4096,
                'temperature' => $params['temperature'] ? $params['temperature'] : 0.8,
                'top_k' => $params['top_k'] ? $params['top_k'] : 200,
                'top_p' => $params['top_p'] ? $params['top_p'] : 0.9,
                'stop_sequences' => ["\\n\\nHuman:"],
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $params['prompt'],
                            ]
                        ]
                    ]
                ]
            ];
            break;

        // Cohere Command parameters
        case strpos($model_id, 'cohere.command') === 0:
            $param_body = [
                'prompt' => $params['prompt'],
                'max_tokens' => $params['max_tokens'] ? $params['max_tokens'] : 4000,
                'temperature' => $params['temperature'] ? $params['temperature'] : 0.8,
                'p' => $params['top_k'] ? $params['top_k'] : 0.9,
                'k' => $params['top_k'] ? $params['top_k'] : 200,
                'stop_sequences' => [],
                'return_likelihoods' => 'NONE',
                'stream' => false
            ];
            break;

        // Meta Llama2 parameters
        case strpos($model_id, 'meta.llama') === 0:
            $param_body = [
                'prompt' => $params['prompt'],
                'max_gen_len' => $params['max_tokens'] ? $params['max_tokens'] : 2048,
                'temperature' => $params['temperature'] ? $params['temperature'] : 0.8,
                'top_p' => $params['top_p'] ? $params['top_p'] : 0.9
            ];
            break;

        // Mistral/Mixtral parameters
        case strpos($model_id, 'mistral') === 0:
            $param_body = [
                'prompt' => $params['prompt'],
                'max_tokens' => $params['max_tokens'] ? $params['max_tokens'] : 4096,
                'temperature' => $params['temperature'] ? $params['temperature'] : 0.8,
                'top_p' => $params['top_p'] ? $params['top_p'] : 0.9,
                'top_k' => $params['top_k'] ? $params['top_k'] : 200,
                'stop' => []
            ];
            break;
    }

    return $param_body;
}

/**
 * Function to parse the response of Amazon Bedrock InvokeModel()
 * 
 * @param string $model_id - Amazon Bedrock model id
 * @param array $response - Amazon Bedrock InvokeModel() response
 * @return array - ['text' => '', 'error' => '']
 */
function my_ai_parse_bedrock_response($model_id, $response) {
    $parsed_response = [];
    switch (true) {
        // Amazon Titan response
        case strpos($model_id, 'amazon.titan') === 0:
            $parsed_response = [
                'error' => null,
                'text' => $response['results'][0]['outputText']
            ];
            break;

        // AI21 labs Jurassic response
        case strpos($model_id, 'ai21.j2') === 0:
            $parsed_response = [
                'error' => null,
                'text' => $response['completions'][0]['data']['text']
            ];
            break;

        // Anthropic Claude response
        case strpos($model_id, 'anthropic.claude') === 0:
            $parsed_response = [
                'error' => null,
                'text' => $response['content'][0]['text'] ?? ''
            ];
            break;

        // Cohere Command response
        case strpos($model_id, 'cohere.command') === 0:
            $parsed_response = [
                'error' => null,
                'text' => $response['generations'][0]['text'] ?? ''
            ];
            break;

        // Meta Llama2 response
        case strpos($model_id, 'meta.llama') === 0:
            $parsed_response = [
                'error' => null,
                'text' => $response['generation'] ?? ''
            ];
            break;

        // Mistral/Mixtral response
        case strpos($model_id, 'mistral') === 0:
            $parsed_response = [
                'error' => null,
                'text' => $response['outputs'][0]['text'] ?? ''
            ];
            break;
    }

    return $parsed_response;
}

/**
 * Function to run inference on Amazon Bedrock
 * 
 * @param BedrockRuntimeClient $client
 * @param string $model_id
 * @param array $params
 * @return array
 */
function my_ai_invoke_bedrock($client, $model_id, $params) {
    try {
        $body = my_ai_build_bedrock_body($model_id, $params);

        $invoke_params = [
            'modelId' => $model_id,
            'contentType' => 'application/json',
            'body' => json_encode($body),
        ];

        $response = $client->invokeModel($invoke_params);
        $response_body = json_decode($response['body'], true);

        // Parse the response based on model id
        return my_ai_parse_bedrock_response($model_id, $response_body);
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Function to combine our system prompt with user prompt. Some of the models
 * has different prompt format.
 * 
 * @param string $model_id
 * @param stirng $user_prompt
 * @return string
 */
function my_ai_build_prompt($model_id, $user_prompt) {
    $system_prompt = <<<SYSTEM_PROMPT
You are an intelligent AI assistant for writing a blog post. You are an expert to generate very long, detailed and SEO optimized article. 

You must take into consideration rules below when generating article:
- The first line of your response should be the title of the blog post followed by a blank line. 
- Title MUST be put within <my_ai_title></my_ai_title> tags.
- The article content MUST be put within <my_ai_content></my_ai_contents> tags.
- The summary of the content MUST be put within <my_ai_summary></my_ai_summary> tags.
- Take a look at the additional instruction inside <query></query> tags to generate the content of the article.
- Article format MUST be in HTML
- Make sure to wrap each paragraph with tag <p></p>.
- Make sure to wrap each heading with tag <h2></h2> or <h3></h3>. Depending on the heading level.
- Important: Skip the preamble from your response. NEVER generate text before the article.

Here is an example of the format:
<example>
<my_ai_title>This is example title</my_ai_title>

<my_ai_content>
<p>This is example of opening paragraph 1.</p>
<p>This is example of opening paragraph 2.</p>

<h2>Sub heading 1</h2>
<p>This is example paragraph 1</p>
<p>This is example paragraph 2</p>
<p>This is example paragraph 3</p>

<h2>Sub heading 2</h2>
<p>This is example paragraph 1</p>
<p>This is example paragraph 2</p>
<p>This is example paragraph 3</p>
<p>This is example paragraph 4</p>
<p>This is example paragraph 5</p>

<h2>Sub heading 3</h2>
<p>This is example paragraph 1</p>
<p>This is example paragraph 2</p>
<p>This is example paragraph 3</p>
<p>This is example paragraph 4</p>

<h2>Sub heading 4</h2>
<p>This is example paragraph 1</p>
<p>This is example paragraph 2</p>
<p>This is example paragraph 3</p>

<h2>Sub heading for conclusion</h2>
<p>This is example conclusion paragraph 1</p>
<p>This is example conclusion paragraph 2</p>
</my_ai_content>

<my_ai_summary>
This is example of the summary of the article.
</my_ai_summary>
</example>

<query>%s</query>

SYSTEM_PROMPT;

    // Add prefix or suffix to the prompt based on the value of model id
    $prefix = ''; $suffix = '';
    switch (true) {
        case strpos($model_id, 'anthropic.claude-v2') === 0:
            $prefix = "\n\nHuman:";
            $suffix = "\n\nAssistant:";
            break;
        
        case strpos($model_id, 'meta.llama') === 0:
            $prefix = "[INST]";
            $suffix = "[/INST]";
            break;
        
        case strpos($model_id, 'mistral') === 0:
            $prefix = "<s>[INST]";
            $suffix = "[/INST]";
            break;
    }

    $final_prompt = $prefix . $system_prompt . $suffix;
    return sprintf($final_prompt, $user_prompt);
}

/**
 * Function to generate AI content in JSON format.
 * 
 * @param BedrockRuntimeClient $client
 * @param array $params
 * @return string
 */
function my_ai_generate_content($client, $params) {
    $model_id = $params['model_id'];

    // Build the prompt based on the model id
    $prompt = my_ai_build_prompt($model_id, $params['prompt']);
    $params['prompt'] = $prompt;

    // Make sure to convert numerical parameters from string to integer/float
    $params['max_tokens'] = intval($params['max_tokens']);
    $params['temperature'] = floatval($params['temperature']);
    $params['top_p'] = floatval($params['top_p']);
    $params['top_k'] = intval($params['top_k']);

    // Invoke the model
    $response = my_ai_invoke_bedrock($client, $model_id, $params);

    return json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
}