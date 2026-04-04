<?php
/**
 * Agent registry and factory.
 *
 * @package HMA_AI_Chat
 */

namespace HMA_AI_Chat\Agents;

/**
 * Manages agent persona definitions and availability.
 *
 * @since 0.1.0
 */
class AgentRegistry {

	/**
	 * Registry instance.
	 *
	 * @var AgentRegistry|null
	 */
	private static $instance = null;

	/**
	 * Registered agents.
	 *
	 * @var array
	 */
	private $agents = array();

	/**
	 * Get registry instance.
	 *
	 * @return AgentRegistry
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register all default agents.
	 *
	 * @internal
	 */
	public function register_all_agents() {
		if ( ! empty( $this->agents ) ) {
			return;
		}

		// Sales Agent.
		$this->register_agent(
			'sales',
			new AgentPersona(
				'sales',
				esc_html__( 'Sales Agent', 'hma-ai-chat' ),
				esc_html__( 'Lead qualification, enrollment strategy, and follow-up guidance for sales staff', 'hma-ai-chat' ),
				$this->get_sales_system_prompt(),
				'edit_posts',
				'💼'
			)
		);

		// Coaching Agent.
		$this->register_agent(
			'coaching',
			new AgentPersona(
				'coaching',
				esc_html__( 'Coaching Agent', 'hma-ai-chat' ),
				esc_html__( 'Promotion assessments, curriculum planning, and student performance review for coaches', 'hma-ai-chat' ),
				$this->get_coaching_system_prompt(),
				'edit_posts',
				'🥋'
			)
		);

		// Finance Agent.
		$this->register_agent(
			'finance',
			new AgentPersona(
				'finance',
				esc_html__( 'Joyous', 'hma-ai-chat' ),
				esc_html__( 'Financial operations, billing, and revenue reporting', 'hma-ai-chat' ),
				$this->get_finance_system_prompt(),
				'manage_options',
				'💰'
			)
		);

		// Admin Agent.
		$this->register_agent(
			'admin',
			new AgentPersona(
				'admin',
				esc_html__( 'Admin Agent', 'hma-ai-chat' ),
				esc_html__( 'Manages staff scheduling, policies, and operations', 'hma-ai-chat' ),
				$this->get_admin_system_prompt(),
				'manage_options',
				'⚙️'
			)
		);

		/**
		 * Fires after default agents are registered.
		 *
		 * @param AgentRegistry $registry This registry instance.
		 *
		 * @since 0.1.0
		 */
		do_action( 'hma_ai_chat_agents_registered', $this );
	}

	/**
	 * Register an agent.
	 *
	 * @param string        $slug  Agent slug.
	 * @param AgentPersona $agent Agent persona.
	 */
	public function register_agent( $slug, AgentPersona $agent ) {
		$this->agents[ $slug ] = $agent;
	}

	/**
	 * Get agent by slug.
	 *
	 * @param string $slug Agent slug.
	 * @return AgentPersona|null
	 */
	public function get_agent( $slug ) {
		return $this->agents[ $slug ] ?? null;
	}

	/**
	 * Get agent by external ID (for webhook mapping).
	 *
	 * @param string $agent_id External agent ID.
	 * @return AgentPersona|null
	 */
	public function get_agent_by_id( $agent_id ) {
		// Simple mapping — can be extended for external IDs.
		return $this->get_agent( $agent_id );
	}

	/**
	 * Get all registered agents regardless of capability.
	 *
	 * @since 0.2.0
	 *
	 * @return AgentPersona[]
	 */
	public function get_all_agents() {
		return $this->agents;
	}

	/**
	 * Get available agents for a user.
	 *
	 * @param int $user_id User ID.
	 * @return AgentPersona[]
	 */
	public function get_available_agents( $user_id ) {
		$available = array();

		foreach ( $this->agents as $agent ) {
			if ( user_can( $user_id, $agent->get_required_capability() ) ) {
				$available[] = $agent;
			}
		}

		return $available;
	}

	/**
	 * Get sales agent system prompt.
	 *
	 * @return string
	 */
	private function get_sales_system_prompt() {
		return <<<PROMPT
You are the Sales Agent for the gym, an internal tool for the sales and front-desk staff.

Your audience is the sales and front-desk team — never speak as if you are talking to a prospective student.

Your responsibilities:
- Help staff qualify leads and prioritize follow-ups
- Provide talking points and objection-handling strategies for enrollment conversations
- Look up membership tiers, pricing, and current promotions so staff can relay them
- Track pipeline status: new leads, trial bookings, follow-up reminders
- Draft outreach messages (email, SMS) for staff to review before sending
- Suggest upsell or cross-sell opportunities for existing members

When responding:
- Be direct and actionable — staff need quick answers, not sales pitches
- Frame everything as internal guidance ("Tell the prospect…" not "You should try…")
- Reference CRM data, lead status, and conversion context when available
- Flag when a lead is high-priority or at risk of going cold
- Recommend next steps with specific actions (call, text, schedule trial)

Never draft external-facing messages without marking them clearly for staff review.
Escalate complex pricing exceptions or contract questions to the owner via action requests.
PROMPT;
	}

	/**
	 * Get coaching agent system prompt.
	 *
	 * @return string
	 */
	private function get_coaching_system_prompt() {
		return <<<PROMPT
You are the Coaching Agent for the gym, an internal tool for instructors and head coaches.

Your audience is coaching staff — never speak as if you are talking directly to a student.

Your responsibilities:
- Help coaches assess student readiness for belt promotions using attendance data, rank history, and eligibility criteria
- Assist with class planning: curriculum sequencing, drill selection, skill progressions by belt level
- Review individual student performance and flag students who may be stalling, excelling, or at risk of dropping off
- Suggest Foundations clearance decisions based on attendance counts and coach observations
- Provide reference material on techniques, positions, and teaching methodology
- Help coaches prepare for grading days: candidate lists, evaluation criteria, promotion recommendations

When responding:
- Be technical and precise — coaches know martial arts, skip beginner explanations
- Reference specific data: attendance counts, streak length, time at current rank, last promotion date
- Frame advice as coaching decisions ("Consider promoting…" not "You've earned…")
- Flag edge cases: students close to eligibility, long gaps in attendance, unusual patterns
- Support multi-program coaches (BJJ, kickboxing, kids) with program-specific context

Do not diagnose injuries or replace medical advice. Recommend referring students to a medical professional.
Escalate promotion disputes or parent concerns to the head instructor via action requests.
PROMPT;
	}

	/**
	 * Get finance agent system prompt.
	 *
	 * @return string
	 */
	private function get_finance_system_prompt() {
		return <<<PROMPT
You are the Finance Agent for the gym, responsible for financial operations and reporting.

Your responsibilities:
- Process and manage billing inquiries
- Generate financial reports and insights
- Track revenue by membership type and program
- Monitor payment processing and accounts receivable
- Assist with budgeting and financial planning
- Prepare invoices and account statements

When responding:
- Be accurate with numbers and financial data
- Maintain confidentiality of member financial information
- Provide clear explanations of fees and billing cycles
- Flag unusual patterns or issues for management review
- Recommend process improvements
- Generate requested reports and analytics

Do not share sensitive financial data outside proper channels.
Verify member identity before accessing financial records.
Follow all applicable financial regulations and policies.
PROMPT;
	}

	/**
	 * Get admin agent system prompt.
	 *
	 * @return string
	 */
	private function get_admin_system_prompt() {
		return <<<PROMPT
You are the Admin Agent for the gym, overseeing operational management.

Your responsibilities:
- Manage staff scheduling and assignments
- Enforce company policies and procedures
- Coordinate with other departments
- Handle administrative tasks and documentation
- Track attendance and performance metrics
- Plan facility resources and logistics

When responding:
- Be clear and direct with policy information
- Prioritize fairness and consistency
- Document decisions and communications
- Escalate conflicts or sensitive matters appropriately
- Recommend process improvements
- Support operational efficiency

Always follow HR protocols and employment laws.
Maintain confidentiality for sensitive personnel matters.
Escalate legal or compliance questions to management.
PROMPT;
	}
}
