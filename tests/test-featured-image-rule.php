<?php

declare( strict_types=1 );

use Mai\PublishRequirements\Context;
use Mai\PublishRequirements\Result;
use Mai\PublishRequirements\Rules\FeaturedImage;
use Mai\PublishRequirements\Severity;

/**
 * @covers \Mai\PublishRequirements\Rules\FeaturedImage
 */
class Test_Featured_Image_Rule extends WP_UnitTestCase {

	private function context_without_image(): Context {
		$prepared = (object) [ 'ID' => 0, 'post_type' => 'post' ];
		$request  = new WP_REST_Request();
		$request['status'] = 'publish';

		return Context::from_rest( $prepared, $request );
	}

	private function context_with_image(): Context {
		$prepared = (object) [ 'ID' => 0, 'post_type' => 'post' ];
		$request  = new WP_REST_Request();
		$request['status']         = 'publish';
		$request['featured_media'] = 7;

		return Context::from_rest( $prepared, $request );
	}

	public function test_identity(): void {
		$rule = new FeaturedImage();

		$this->assertSame( 'featured_image', $rule->id() );
		$this->assertSame( [ 'post' ], $rule->post_types() );
	}

	public function test_fails_when_no_featured_image(): void {
		$result = ( new FeaturedImage() )->check( $this->context_without_image() );

		$this->assertInstanceOf( Result::class, $result );
		$this->assertNotSame( '', $result->message );
	}

	public function test_passes_when_featured_image_present(): void {
		$this->assertNull( ( new FeaturedImage() )->check( $this->context_with_image() ) );
	}

	/**
	 * Severity is the site's call for a binary rule, taken at registration.
	 */
	public function test_blocks_by_default(): void {
		$result = ( new FeaturedImage() )->check( $this->context_without_image() );

		$this->assertSame( Severity::Block, $result->severity );
		$this->assertTrue( $result->is_block() );
	}

	public function test_warns_when_registered_that_way(): void {
		$result = ( new FeaturedImage( Severity::Warn ) )->check( $this->context_without_image() );

		$this->assertSame( Severity::Warn, $result->severity );
		$this->assertFalse( $result->is_block() );
	}

	/**
	 * A site that wants the answer to depend on the post subclasses rather than
	 * reimplementing the detection.
	 */
	public function test_a_subclass_can_decide_per_post(): void {
		$rule = new class() extends FeaturedImage {
			public function check( Context $context ): ?Result {
				$result = parent::check( $context );

				return $result ? new Result( Severity::Warn, 'add a picture at some point' ) : null;
			}
		};

		$result = $rule->check( $this->context_without_image() );

		$this->assertSame( Severity::Warn, $result->severity );
		$this->assertSame( 'add a picture at some point', $result->message );
	}
}
