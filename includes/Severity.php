<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements;

defined( 'ABSPATH' ) || exit;

/**
 * How serious an unmet requirement is.
 *
 * The rule decides, per post. A site that wants a different answer registers the
 * rule differently or writes its own; there is deliberately no setting that
 * overrules a rule, because a rule with thresholds (warn at five embeds, block
 * at eight) would be flattened to one behaviour by any such setting.
 */
enum Severity: string {

	/** Say something. The post publishes. */
	case Warn = 'warn';

	/** Refuse the publish. */
	case Block = 'block';
}
