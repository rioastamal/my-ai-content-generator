function my_ai_sidebar_init() {
    var wpBaseUrl = window.location.href.split('/wp-admin/')[0];
    // myAiSelectedFoundationModels Javascript variable are injected via WordPress hooks 'enqueue_block_editor_assets'
    var foundationModels = myAiSelectedFoundationModels;

    var generate_my_ai_content = async (foundationModel, params) => {
        var queryStringRestRoute = new URLSearchParams({
            rest_route: '/my-ai-content-generator/v1/contents',
            _wpnonce: wpApiSettings.nonce
        });
        var myAiApiUrl = wpBaseUrl + '/?' + queryStringRestRoute;

        // Call AI content generator REST endpoint
        var response = await fetch(myAiApiUrl.toString(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                model_id: foundationModel,
                prompt: params.prompt,
                temperature: params.temperature,
                top_p: params.top_p,
                top_k: params.top_k || null,
                max_tokens: params.max_tokens
            }),
            credentials: 'include'
        });

        var data = await response.json();

        // If the data is still in string, we need to convert it to JSON
        if (typeof data === 'string') {
            try {
                var json = JSON.parse(data);
                return json;
            } catch (e) {}
        }

        return data;
    };

    // Object to store foundation model family configuration such as: 
    // temperature, top_p, top_k and max_tokens.
    // Each model family has default value and maximum value of the configuration.
    // As an example: 'amazon.titan' family 
    var foundationModelConfig = {
        'amazon.titan': {
            temperature: { min: 0, max: 1, default: 0.9 },
            top_p: { min: 0, max: 1, default: 1 },
            top_k: null,
            max_tokens: { min: 0, max: 4096, default: 2048 }
        },

        'ai21.j2': {
            temperature: { min: 0, max: 1, default: 0.7 },
            top_p: { min: 0, max: 1, default: 1 },
            top_k: null,
            max_tokens: { min: 0, max: 8191, default: 2048 }
        },

        'anthropic.claude': {
            temperature: { min: 0, max: 1, default: 1 },
            top_p: { min: 0, max: 1, default: 0.99 },
            top_k: { min: 0, max: 500, default: 200 },
            max_tokens: { min: 0, max: 4096, default: 2048 }
        },

        'cohere.command': {
            temperature: { min: 0, max: 1, default: 0.75 },
            top_p: { min: 0, max: 1, default: 0 },
            top_k: { min: 0, max: 500, default: 200 },
            max_tokens: { min: 0, max: 4000, default: 2048 }
        },

        'meta.llama': {
            temperature: { min: 0, max: 1, default: 0.5 },
            top_p: { min: 0, max: 1, default: 0.9 },
            top_k: null,
            max_tokens: { min: 0, max: 2048, default: 2048 }
        },

        'mistral': {
            temperature: { min: 0, max: 1, default: 0.5 },
            top_p: { min: 0, max: 1, default: 0.9 },
            top_k: { min: 0, max: 200, default: 200 },
            max_tokens: { min: 0, max: 8192, default: 2048 }
        }
    };

    /**
     * Get the current short model id name from the selected model
     * @param {string} foundationModel
     * @returns {string}
     */
    var getModelFamily = (foundationModel) => {
        // Get the current short model id name from the selected model
        var modelId = '';
        for (var modelFamily of Object.keys(foundationModelConfig)) {
            if (foundationModel.indexOf(modelFamily) > -1) {
                modelId = modelFamily;
                break;
            }
        }

        return modelId;
    }

    // Initial short model name used by the foundationModelConfig
    var modelFamily = getModelFamily(foundationModels[0]);

    // Register the plugin elements into the Gutenberg sidebar
    window.wp.plugins.registerPlugin("my-ai-sidebar", {
        render: () => {
            var [currentModelState, setCurrentModelState] = React.useState(foundationModels[0]);
            var [promptState, setPromptState] = React.useState('');
            var [temperatureState, setTemperatureState] = React.useState(foundationModelConfig[modelFamily].temperature);
            var [topPState, setTopPState] = React.useState(foundationModelConfig[modelFamily].top_p);
            var [topKState, setTopKState] = React.useState(foundationModelConfig[modelFamily].top_k);
            var [maxTokensState, setMaxTokensState] = React.useState(foundationModelConfig[modelFamily].max_tokens);
            var [buttonEnabledState, setButtonEnabledState] = React.useState(false);
            var [generatingState, setGeneratingState] = React.useState(false);

            // Create array of options element based on foundation models list
            var optionsElement = foundationModels.map(modelId => {
                return React.createElement('option', { value: modelId }, modelId);
            });
            var selectElement = React.createElement('select', {
                id: 'my_ai_model_id', style: { marginBottom: '10px', display: 'block', width: '95%' }, 
                value: currentModelState,
                onChange: (e) => {
                    // Get the selected foundation model
                    var foundationModel = e.target.value;
                    console.log('FM -> ', foundationModel);

                    var modelId = getModelFamily(foundationModel);
                    setTemperatureState(foundationModelConfig[modelId].temperature);
                    setTopPState(foundationModelConfig[modelId].top_p);
                    setTopKState(foundationModelConfig[modelId].top_k);
                    setMaxTokensState(foundationModelConfig[modelId].max_tokens);
                    setCurrentModelState(foundationModel);
                }
            }, optionsElement);
            var labelSelectModelElement = React.createElement('label', { display: 'block' }, 'Select foundation model:');
            var labelPromptElement = React.createElement('label', { display: 'block' }, 'Input prompt:');
            var inputPromptElement = React.createElement('textarea', {
                id: 'my_ai_prompt', style: { marginBottom: '10px', display: 'block', width: '95%', height: '150px' },
                placeholder: 'Write an article about the benefits of meditation',
                value: promptState,
                onChange: (e) => {
                    // Enable the generate button if the prompt is not empty
                    if (e.target.value.trim().length === 0) {
                        setButtonEnabledState(false);
                        return;
                    }

                    setPromptState(e.target.value);
                    setButtonEnabledState(true);
                }
            });

            var buttonElement = React.createElement('button', {
                id: 'my_ai_btn_generate', display: 'block', className: 'components-button is-primary',
                disabled: !buttonEnabledState,
                onClick: async (e) => {
                    var foundationModelId = document.getElementById('my_ai_model_id').value;
                    var prompt = document.getElementById('my_ai_prompt').value;

                    setGeneratingState(true)

                    // When the button clicked the label should change to "Generating...", once finished
                    // it should back to "Generate"
                    e.target.innerText = 'Generating...';
                    e.target.disabled = true;

                    // Call generate_my_ai_content to fetch the generated content via API
                    // Construct the foundation model parameters to send to the API
                    var modelParams = {
                        prompt: prompt,
                        temperature: temperatureState.default,
                        top_p: topPState.default,
                        top_k: topKState ? topKState.default : null,
                        max_tokens: maxTokensState.default
                    }

                    var response = await generate_my_ai_content(foundationModelId, modelParams);
                    console.log(response);

                    // The response contains two properties 'error' and 'text'
                    if (response.error) {
                        setGeneratingState(false);
                        alert(response.error);
                        e.target.innerText = 'Generate';
                        e.target.disabled = false;
                        document.getElementById('my_ai_prompt').focus();

                        return;
                    }

                    // If there is no <my_ai_title>, </my_ai_title>, <my_ai_content>, and <my_ai_content> tag 
                    // in the response, then dispatch everything to the block editor.
                    // Otherwise, extract the title and content from the response.text
                    var validFormat = response.text.indexOf('<my_ai_title>') !== -1 && 
                                        response.text.indexOf('</my_ai_title>') !== -1 && 
                                        response.text.indexOf('<my_ai_content>') !== -1 && 
                                        response.text.indexOf('</my_ai_content>') !== -1;

                    if (! validFormat) {
                        window.wp.data.dispatch('core/editor').editPost({ title: '[Unknown]' });
                        window.wp.data.dispatch('core/block-editor').resetBlocks( window.wp.blocks.parse( response.text ));

                        e.target.innerText = 'Generate';
                        e.target.disabled = false;
                        document.getElementById('my_ai_prompt').focus();

                        setGeneratingState(false);

                        return;
                    }

                    // Extract the title from the response.text using substring.
                    // The title inside the <my_ai_title>THE_TITLE</my_ai_title>
                    var title = response.text.substring(response.text.indexOf('<my_ai_title>') + '<my_ai_title>'.length, response.text.indexOf('</my_ai_title>'));
                    title = title.trim();

                    // Extract the content from the response.text using substring.
                    // The content inside the <my_ai_content>THE_CONTENT</my_ai_content>
                    var content = response.text.substring(response.text.indexOf('<my_ai_content>') + '<my_ai_content>'.length, response.text.indexOf('</my_ai_content>'));
                    content = content.trim();

                    // Dispatch the title into Gutenberg using wp.data.dispatch('core/editor').editPost()
                    window.wp.data.dispatch('core/editor').editPost({ title: title });

                    // Reset the Gutenberg content and pass our content as the replacement
                    window.wp.data.dispatch('core/block-editor').resetBlocks( window.wp.blocks.parse( content ));

                    // If the response.text has the summary then dispatch the core/editor excerpt
                    if (response.text.indexOf('<my_ai_summary>') !== -1) {
                        var summary = response.text.substring(response.text.indexOf('<my_ai_summary>') + '<my_ai_summary>'.length, response.text.indexOf('</my_ai_summary>'));
                        summary = summary.trim();

                        window.wp.data.dispatch('core/editor').editPost({ excerpt: summary });
                    }

                    e.target.innerText = 'Generate';
                    e.target.disabled = false;
                    document.getElementById('my_ai_prompt').focus();

                    setGeneratingState(false);
                }
            }, 'Generate');

            var spanTemperatureElement = React.createElement('span', {
                id: 'my_ai_temp_span', style: { fontWeight: 'bold' },
            }, temperatureState.default);
            var labelTemperatureElement = React.createElement('label', { display: 'block' }, 
                'Temperature: ', spanTemperatureElement);
            var inputTemperatureElement = React.createElement('input', {
                id: 'my_ai_temp', type: 'range',
                min: temperatureState.min,
                max: temperatureState.max,
                step: '0.1',
                value: temperatureState.default,
                style: { marginBottom: '10px', display: 'block', width: '95%' },
                onChange: (e) => {
                    setTemperatureState({
                        min: temperatureState.min,
                        max: temperatureState.max,
                        default: e.target.value
                    });
                }
            });

            var spanTopPElement = React.createElement('span', {
                id: 'my_ai_top_p_span', style: { fontWeight: 'bold' },
            }, topPState.default);
            var labelTopPElement = React.createElement('label', { display: 'block' },
                'Top P: ', spanTopPElement);
            var inputTopPElement = React.createElement('input', {
                id: 'my_ai_top_p', type: 'range',
                min: topPState.min,
                max: topPState.max,
                step: '0.1',
                value: topPState.default,
                style: { marginBottom: '10px', display: 'block', width: '95%' },
                onChange: (e) => {
                    setTopPState({
                        min: topPState.min,
                        max: topPState.max,
                        default: e.target.value
                    });
                }
            });

            var spanTopKElement = React.createElement('span', {
                id: 'my_ai_top_k_span', style: { fontWeight: 'bold', color: topKState ? 'inherit' : 'red' },
            }, topKState ? topKState.default : 0);
            var labelTopKElement = React.createElement('label', { 
                style: { 'display': topKState ? 'inline' : 'none' }
                }, 'Top K: ', spanTopKElement);
            var inputTopKElement = React.createElement('input', {
                id: 'my_ai_top_k', type: 'range',
                min: topKState ? topKState.min : null,
                max: topKState ? topKState.max : null,
                step: 1,
                value: topKState ? topKState.default : 0,
                style: { marginBottom: '10px', display: topKState ? 'block' : 'none' , width: '95%' },
                onChange: (e) => {
                    if (topKState) {
                        setTopKState({
                            min: topKState.min,
                            max: topKState.max,
                            default: e.target.value
                        });
                    }
                }
            });

            var spanMaxTokensElement = React.createElement('span', {
                id: 'my_ai_max_tokens_span', style: { fontWeight: 'bold' },
                }, maxTokensState.default);
            var labelMaxTokensElement = React.createElement('label', { display: 'block' },
                'Max Tokens: ', spanMaxTokensElement);
            var inputMaxTokensElement = React.createElement('input', {
                id: 'my_ai_max_tokens', type: 'range',
                min: maxTokensState.min,
                max: maxTokensState.max,
                step: 1,
                value: maxTokensState.default,
                style: { marginBottom: '10px', display: 'block', width: '95%' },
                onChange: (e) => {
                    setMaxTokensState({
                        min: maxTokensState.min,
                        max: maxTokensState.max,
                        default: e.target.value
                    });
                }
            });

            // Array of model config elements (temperature, top p, top k, and max tokens)
            var modelConfigElements = [
                labelTemperatureElement, inputTemperatureElement,
                labelTopPElement, inputTopPElement,
                labelTopKElement, inputTopKElement,
                labelMaxTokensElement, inputMaxTokensElement
            ];

            var modelConfigElement = React.createElement('div', {
                id: 'my_ai_model_config', display: 'block', style: { marginBottom: '10px' }
            }, ...modelConfigElements)

            var myAiElement = React.createElement('div', {
                style: { paddingLeft: '16px', paddingRight: '16px', marginTop: '20px' },
                id: 'my_ai_elements_container'
                }, 
                labelSelectModelElement,
                selectElement, 
                labelPromptElement,
                inputPromptElement,
                modelConfigElement,
                buttonElement
            ); // myAiElement

            var pluginInfo = React.createElement(window.wp.editPost.PluginSidebar, {
                name: 'my-ai-sidebar-element',
                title: 'My AI Content Generator',
                icon: 'welcome-write-blog'
            }, myAiElement);
    
            return pluginInfo;
        }
    });
}

my_ai_sidebar_init();
