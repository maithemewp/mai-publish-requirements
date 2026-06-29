<?php

declare( strict_types=1 );

use Mai\PublishRequirements\Context;
use Mai\PublishRequirements\Rules\FeaturedImage;

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
		$this->assertSame( [ 'post' ], $rule->default_post_types() );
	}

	public function test_fails_when_no_featured_image(): void {
		$message = ( new FeaturedImage() )->check( $this->context_without_image() );

		$this->assertNotNull( $message );
		$this->assertIsString( $message );
	}

	public function test_passes_when_featured_image_present(): void {
		$message = ( new FeaturedImage() )->check( $this->context_with_image() );

		$this->assertNull( $message );
	}
}
