<?php
/**
 * Temporary debug endpoint for Gandalf. DELETE AFTER DEBUGGING.
 * Accessible at: /wp-json/debug/gandalf
 */
add_action('rest_api_init', function() {
    register_rest_route('debug', '/gandalf', [
        'methods'             => 'GET',
        'callback'            => function() {
            $results = [];

            // 1. Check tables exist
            global $wpdb;
            $tables = ['hma_ai_conversations', 'hma_ai_messages', 'hma_ai_pending_actions'];
            foreach ($tables as $t) {
                $full = $wpdb->prefix . $t;
                $exists = $wpdb->get_var("SHOW TABLES LIKE '$full'");
                $results["table_$t"] = $exists ? 'EXISTS' : 'MISSING';
            }

            // 2. Check API key
            $key = get_option('hma_ai_chat_anthropic_api_key', '');
            $results['api_key'] = $key ? 'SET (' . substr($key, 0, 12) . '...)' : 'NOT_SET';

            // 3. Check agents registered
            if (class_exists('HMA_AI_Chat\\Agents\\AgentRegistry')) {
                $reg = \HMA_AI_Chat\Agents\AgentRegistry::instance();
                $all = $reg->get_all_agents();
                $results['agents'] = array_keys($all);
            } else {
                $results['agents'] = 'CLASS_NOT_FOUND';
            }

            // 4. Test ClaudeClient
            if (class_exists('HMA_AI_Chat\\API\\ClaudeClient')) {
                try {
                    $client = new \HMA_AI_Chat\API\ClaudeClient();
                    $r = $client->send('You are a test.', [['role' => 'user', 'content' => 'Say OK']]);
                    if (is_wp_error($r)) {
                        $results['claude_test'] = 'ERROR: ' . $r->get_error_message();
                    } else {
                        $results['claude_test'] = 'OK: ' . substr($r['response'], 0, 100);
                    }
                } catch (\Throwable $e) {
                    $results['claude_test'] = 'EXCEPTION: ' . $e->getMessage();
                }
            } else {
                $results['claude_test'] = 'CLASS_NOT_FOUND';
            }

            // 5. PHP version
            $results['php_version'] = PHP_VERSION;
            $results['wp_ai_client'] = function_exists('wp_ai_client_prompt') ? 'YES' : 'NO';

            return $results;
        },
        'permission_callback' => '__return_true',
    ]);
});
