<?php

namespace CobraAI\Features\Emailmarketing;

defined('ABSPATH') || exit;

/**
 * Dispatches a campaign: resolves target users and enqueues emails.
 */
class CampaignDispatcher
{
    private Feature $feature;

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
    }

    // -------------------------------------------------------------------------
    // DISPATCH
    // -------------------------------------------------------------------------

    /**
     * Dispatch a single campaign by ID.
     * Marks it as 'sending', enqueues per-user emails, then marks 'sent'.
     */
    public function dispatch(int $campaign_id): bool
    {
        $campaign = $this->feature->campaign_repo->get_by_id($campaign_id);
        if (!$campaign) {
            return false;
        }

        if (!in_array($campaign['status'], ['scheduled', 'draft'], true)) {
            return false;
        }

        // Mark as sending
        $this->feature->campaign_repo->update_status($campaign_id, 'sending');

        $users      = $this->get_audience_users($campaign);
        $total      = count($users);
        $email_type = 'campaign_' . $campaign_id;
        $enqueued   = 0;

        foreach ($users as $user_id) {
            // Skip unsubscribed users
            if ($this->feature->prefs && $this->feature->prefs->is_unsubscribed($user_id)) {
                continue;
            }

            $ok = $this->feature->queue->enqueue(
                $user_id,
                $email_type,
                time(),
                [
                    'campaign_id' => $campaign_id,
                    'subject'     => $campaign['subject'],
                    'body'        => $campaign['body'],
                ]
            );

            if ($ok) {
                $enqueued++;
            }
        }

        // Mark as sent with counts
        $this->feature->campaign_repo->update_status($campaign_id, 'sent', [
            'sent_count'  => 0,        // actual sends tracked when queue processes
            'total_count' => $enqueued,
        ]);

        return true;
    }

    // -------------------------------------------------------------------------
    // AUDIENCE RESOLUTION
    // -------------------------------------------------------------------------

    /**
     * Returns user IDs matching the campaign's audience filters.
     *
     * @param array $campaign Row from cobra_email_campaigns
     * @return int[]
     */
    public function get_audience_users(array $campaign): array
    {
        $type    = $campaign['audience_type'] ?? 'all';
        $filters = json_decode($campaign['audience_filters'] ?? '{}', true) ?: [];

        $args = [
            'fields'  => 'ID',
            'number'  => -1,
            'orderby' => 'ID',
        ];

        if ($type === 'role' && !empty($filters['roles'])) {
            $args['role__in'] = (array) $filters['roles'];
        }

        if ($type === 'meta' && !empty($filters['meta_key'])) {
            $args['meta_query'] = [
                [
                    'key'     => sanitize_key($filters['meta_key']),
                    'value'   => $filters['meta_value'] ?? '',
                    'compare' => $filters['meta_compare'] ?? '=',
                ],
            ];
        }

        // Filter: verified email only (_email_verified meta set to truthy value)
        if (!empty($filters['verified_only'])) {
            $verified_clause = [
                'key'     => '_email_verified',
                'value'   => '0',
                'compare' => '>',
                'type'    => 'NUMERIC',
            ];
            if (isset($args['meta_query'])) {
                $args['meta_query']['relation'] = 'AND';
                $args['meta_query'][]           = $verified_clause;
            } else {
                $args['meta_query'] = [$verified_clause];
            }
        }

        $users = get_users($args);

        return array_map('intval', is_array($users) ? $users : []);
    }

    // -------------------------------------------------------------------------
    // AI CONTENT GENERATION
    // -------------------------------------------------------------------------

    /**
     * Generate campaign body using the AI feature.
     * Returns ['success' => true, 'content' => '...'] or ['success' => false, 'message' => '...']
     *
     * @param string $prompt      Admin's prompt
     * @param int[]  $post_ids    Optional post/page IDs for context
     * @return array
     */
    public function generate_with_ai(string $prompt, array $post_ids = []): array
    {
        // Build context from posts
        $context = '';
        foreach ($post_ids as $post_id) {
            $post = get_post((int) $post_id);
            if (!$post) {
                continue;
            }
            $excerpt = !empty($post->post_excerpt)
                ? $post->post_excerpt
                : wp_trim_words(wp_strip_all_tags($post->post_content), 60);
            $context .= "\n- " . $post->post_title . ': ' . $excerpt;
        }

        $full_prompt  = $prompt;
        if (!empty(trim($context))) {
            $full_prompt .= "\n\nContexte (pages/articles du site) :\n" . trim($context);
        }
        $full_prompt .= "\n\nRéponds uniquement avec le corps de l'email en HTML simple (sans balises <html>/<head>/<body>). Utilise {{prenom}} pour personnaliser.";

        // Get AI feature
        $cobra = \CobraAI\CobraAI::instance();
        /** @var \CobraAI\Features\AI\Feature|null $ai_feature */
        $ai_feature = $cobra->get_feature('ai');

        if (!$ai_feature || empty($ai_feature->manager)) {
            return ['success' => false, 'message' => __('La fonctionnalité AI n\'est pas activée.', 'cobra-ai')];
        }

        try {
            // Use default active provider
            $providers = $ai_feature->manager->get_active_providers();
            if (empty($providers)) {
                return ['success' => false, 'message' => __('Aucun fournisseur AI actif configuré.', 'cobra-ai')];
            }

            $provider = array_key_first($providers);
            $result   = $ai_feature->manager->process_request($provider, $full_prompt, [
                'max_tokens'  => 800,
                'temperature' => 0.7,
            ]);

            return ['success' => true, 'content' => wp_kses_post($result['content'] ?? '')];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
