<?php
declare(strict_types=1);
/**
 * Individual agent persona definition.
 *
 * @package HMA_AI_Chat
 */

namespace HMA_AI_Chat\Agents;

/**
 * Represents a single AI agent with its system prompt and capabilities.
 *
 * @since 0.1.0
 */
class AgentPersona {

	/**
	 * Agent slug/identifier.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Agent display name.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Agent description.
	 *
	 * @var string
	 */
	private $description;

	/**
	 * System prompt for the agent.
	 *
	 * @var string
	 */
	private $system_prompt;

	/**
	 * Required user capability to access this agent.
	 *
	 * @var string
	 */
	private $required_capability;

	/**
	 * Icon emoji or dashicon class.
	 *
	 * @var string
	 */
	private $icon;

	/**
	 * Constructor.
	 *
	 * @param string $slug                  Agent slug.
	 * @param string $name                  Agent display name.
	 * @param string $description           Agent description.
	 * @param string $system_prompt         System prompt for Claude.
	 * @param string $required_capability   Required user capability.
	 * @param string $icon                  Icon emoji/class.
	 */
	public function __construct(
		$slug,
		$name,
		$description,
		$system_prompt,
		$required_capability = 'edit_posts',
		$icon = '🤖'
	) {
		$this->slug                  = $slug;
		$this->name                  = $name;
		$this->description           = $description;
		$this->system_prompt         = $system_prompt;
		$this->required_capability   = $required_capability;
		$this->icon                  = $icon;
	}

	/**
	 * Get agent slug.
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Get agent name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get agent description.
	 *
	 * @return string
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Get required capability.
	 *
	 * @return string
	 */
	public function get_required_capability() {
		return $this->required_capability;
	}

	/**
	 * Get icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return $this->icon;
	}

	/**
	 * Get system prompt with dynamic context.
	 *
	 * @return string
	 */
	public function get_system_prompt() {
		$site_name = get_bloginfo( 'name' );
		$site_url  = get_site_url();
		$current_date = wp_date( 'Y-m-d H:i:s' );

		$context = <<<CONTEXT
Current Date: {$current_date}
Site: {$site_name}
Site URL: {$site_url}
CONTEXT;

		return "{$this->system_prompt}\n\n{$context}";
	}
}
