<?php
/**
 * features.php — Feature flag definitions.
 *
 * Each deployment toggles modules on/off via .env.
 * Code should call feature('ea_compliance') rather than reading directly.
 *
 * Adding a new flag:
 *   1. Add the env var to .env.example
 *   2. Add the key to this array (env name → flag name)
 *   3. Use `feature('flag_name')` everywhere in code
 */

return [

    // Module-level flags
    'ea_compliance'    => env('FEATURE_EA_COMPLIANCE', false),
    'tetfund'          => env('FEATURE_TETFUND', false),
    'nuc'              => env('FEATURE_NUC', false),
    'ptdf'             => env('FEATURE_PTDF', false),
    'strategic_plan'   => env('FEATURE_STRATEGIC_PLAN', false),
    'south_sudan_gov'  => env('FEATURE_SOUTH_SUDAN_GOV', false),

    // Cross-cutting features
    'ai_chat'          => env('FEATURE_AI_CHAT', false),
    'ai_qc'            => env('AI_QC_ENABLED', true),
    'ai_auto_narrative'=> env('AI_AUTO_NARRATIVE_ENABLED', true),
    'voice_notes'      => env('FEATURE_VOICE_NOTES', false),

    // Specific behaviour toggles (rare — prefer module flags)
    'kobo_integration' => true,  // hardcoded on for now; .env override possible later
];
