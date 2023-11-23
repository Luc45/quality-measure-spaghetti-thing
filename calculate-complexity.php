<?php
if ( file_exists( __DIR__ . '/data.json' ) ) {
	$GLOBALS['data'] = json_decode( file_get_contents( __DIR__ . '/data.json' ), true );
} else {
	$GLOBALS['data'] = [];
}

$GLOBALS['vendor_directories'] = [
	'vendor',
	'node_modules',
	'vendor_prefixed',
];

register_shutdown_function( static function () {
	file_put_contents( __DIR__ . '/data.json', json_encode( $GLOBALS['data'], JSON_PRETTY_PRINT ) );
} );

$csv = load_csv();
maybe_download( $csv );

if ( isset( $argv[1] ) ) {
	if ( ! function_exists( $argv[1] ) ) {
		throw new Exception( "Invalid action" );
	}
	$argv[1]();
}

function load_csv(): array {
	$csvFile = __DIR__ . '/quality-vs-ratings.csv';
	$csvData = [];

	if ( ( $handle = fopen( $csvFile, 'r' ) ) !== false ) {
		// Get the first row of the CSV file as column headers
		$headers = fgetcsv( $handle );
		// Loop through each row of the CSV file
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			// Combine the header and row arrays to create an associative array
			// Slug is the last part of the URL.
			$slug = explode( '/', $row[1] );
			$slug = end( $slug );

			if ( empty( $slug ) ) {
				throw new Exception( "Invalid slug" );
			}

			$csvData[ $slug ] = array_combine( $headers, $row );
		}
		fclose( $handle );
	}

	return $csvData;
}

function maybe_download( $csv ) {
	$repos = [];

	foreach ( $csv as $row ) {
		$repos[ $row['Extension'] ] = $row['Repo'];
	}

	foreach ( $repos as $name => $repo ) {
		$repo = rtrim( $repo, '/' );

		// Slug is the last part of the URL.
		$slug = explode( '/', $repo );
		$slug = end( $slug );

		// Make $repo_url, which converts "https://github.com/woocommerce/woocommerce-subscriptions" to "git@github.com:woocommerce/woocommerce-subscriptions.git"
		$repo_url = str_replace( 'https://github.com/', 'git@github.com:', $repo );
		$repo_url .= '.git';

		$project_dir = __DIR__ . "/repos/$slug";

		if ( ! file_exists( $project_dir ) ) {
			passthru( "git clone $repo $project_dir" );
			sleep( 1 );

			if ( ! file_exists( $project_dir ) ) {
				throw new Exception( "Failed to clone $repo" );
			}
		}

		if ( ! isset( $GLOBALS['data'][ $name ] ) ) {
			$GLOBALS['data'][ $name ] = [
				'slug'       => $slug,
				'repo_dir'   => $project_dir,
				'plugin_dir' => find_plugin_entrypoint( $project_dir ),
				'url'        => $repo_url,
			];
		}
	}
}

function find_plugin_entrypoint( $dir ) {
	$dir = rtrim( $dir, '/' ) . '/';

	$hardcoded_map = [
		'/repos/woocommerce/' => 'plugins/woocommerce',
	];

	// See if there's a hardcoded mapping for this repo.
	foreach ( $hardcoded_map as $repo => $p ) {
		if ( stripos( $dir, $repo ) !== false ) {
			echo "[Hardcoded] Found entry point in $dir\n";

			return rtrim( $dir, '/' ) . "/$p";
		}
	}

	// This pattern matches the standard WordPress plugin header.
	$pattern = '/^[\s\*]*Plugin Name:\s?([^\n]{1,50})/mi';

	// Search for PHP files in the specified directory.
	$files = glob( "$dir/*.php" );
	foreach ( $files as $file ) {
		// Read the contents of the file.
		$contents = file_get_contents( $file );
		// Check if the file contains the plugin header.
		if ( preg_match( $pattern, $contents ) ) {
			echo "[Maindir] Found entry point in $file\n";

			return dirname( $file );
		}
	}

	echo "[Info] No entry point found in $dir. Searching sub-dirs...\n";

	// If no plugin file is found in the root, search within the first level of directories.
	$subdirs = glob( "$dir/*", GLOB_ONLYDIR );
	foreach ( $subdirs as $subdir ) {
		$files = glob( "$subdir/*.php" );
		foreach ( $files as $file ) {
			$contents = file_get_contents( $file );
			if ( preg_match( $pattern, $contents ) ) {
				echo "[Subdir] Found entry point in $file\n";

				return dirname( $file );
			}
		}
	}

	throw new Exception( "No entry point found in $dir" );
}

function report() {
	$playwright = [];

	foreach ( $GLOBALS['data'] as $plugin_name => &$plugin_data ) {
		$plugin_dir         = $plugin_data['plugin_dir'];
		$vendor_directories = $GLOBALS['vendor_directories'];
		$test_directories   = [
			'e2e'  => [
				'e2e',
				'e2e-pw',
				'e2e-playwright',
				'acceptance',
			],
			'unit' => [
				'unit',
				'unit-tests',
				'phpunit',
				'Unit',
			]
		];

		if ( file_exists( $plugin_dir . '/tests' ) ) {
			$it = new DirectoryIterator( $plugin_dir . '/tests' );

			$plugin_data['test_types'] = [
				'known'   => [],
				'unknown' => [],
			];

			foreach ( $it as $i ) {
				$is_known = false;

				/** @var SplFileObject $i */
				if ( $i->isDir() && ! $i->isDot() ) {
					foreach ( $test_directories as $type => $possible_values ) {
						if ( in_array( $i->getBasename(), $possible_values, true ) ) {
							$is_known                                                = true;
							$plugin_data['test_types']['known'][ $type ]             = call_user_func( "get_{$type}_framework_info", $i->getPathname() );
							$plugin_data['test_types']['known'][ $type ]['basename'] = $i->getBasename();
							$plugin_data['test_types']['known'][ $type ]['path']     = $i->getPathname();
						}
					}

					if ( ! $is_known ) {
						$plugin_data['test_types']['unknown'][] = $i->getBasename();
					}
				}
			}
		}
	}
}

function get_e2e_framework_info( string $path ): array {
	$info = [
		'framework' => '',
	];

	$it = new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS );
	$it = new RecursiveIteratorIterator( $it );

	$find_framework = static function ( string $path, RecursiveIteratorIterator $iterator ) {
		// Hardcode the puppeteers to not have to identify them.
		$puppeteers = [
			'repos/woocommerce-payments',
			'repos/woocommerce-services',
			'repos/woocommerce-checkout-field-editor',
		];
		foreach ( $puppeteers as $p ) {
			if ( stripos( $path, $p . '/' ) !== false ) {
				return 'Puppeteer';
			}
		}

		foreach ( $iterator as $file ) {
			if ( $file->isDir() ) {
				continue; // Skip directories
			}

			$fileExtension = strtolower( $file->getExtension() );
			$filePath      = $file->getPathname();

			// Read the file content based on the file type
			$fileContent = file_get_contents( $filePath );

			// Look for the Codeception files, which should be "php" files ending in "Cest"
			if ( $fileExtension === 'php' && strpos( $file->getPathname(), 'Cest.php' ) !== false ) {
				return 'Codeception';
			}

			// Look for the playwright in JS files
			if ( $fileExtension === 'js' && strpos( $fileContent, 'playwright' ) !== false ) {
				return 'Playwright';
			}
		}
	};

	$info['framework'] = $find_framework( $path, $it );

	$count_Codeception_tests = static function ( RecursiveIteratorIterator $it ): array {
		$count = [
			'cests'         => 0,
			'scenarios'     => 0,
			'cests_loc'     => 0,
			'scenarios_loc' => 0
		];

		foreach ( $it as $file ) {
			if ( $file->isFile() ) {
				$filename = $file->getFilename();
				// Count the number of Cest tests and their LOC.
				if ( preg_match( '/Cest\.php$/i', $filename ) ) {
					$contents = file_get_contents( $file->getPathname() );
					// Count the number of test methods in the Cest file.
					$count['cests'] += substr_count( $contents, 'public function' );
					// Count non-empty lines of code in the Cest file.
					$count['cests_loc'] += substr_count( trim( $contents ), "\n" ) + 1; // +1 for the first line if file is not empty
				}
				// Count the number of Gherkin scenarios and their LOC.
				if ( preg_match( '/\.feature$/i', $filename ) ) {
					$contents = file_get_contents( $file->getPathname() );
					// Count the number of scenarios in the feature file.
					$count['scenarios'] += substr_count( $contents, "Scenario:" );
					// Count non-empty lines of code in the feature file.
					$count['scenarios_loc'] += substr_count( trim( $contents ), "\n" ) + 1; // +1 for the first line if file is not empty
				}
			}
		}

		return $count;
	};


	$count_Playwright_tests = static function ( RecursiveIteratorIterator $it ): array {
		$count = [
			'tests'     => 0,
			'tests_loc' => 0
		];

		foreach ( $it as $file ) {
			if ( $file->isFile() && $file->getExtension() === 'js' ) {
				$filename = $file->getFilename();
				// Count the number of Playwright tests in files that end with .spec.js or .test.js.
				if ( preg_match( '/\.(spec|test)\.js$/i', $filename ) ) {
					$contents = file_get_contents( $file->getPathname() );
					// Count the number of test definitions.
					$count['tests'] += substr_count( $contents, 'test(' );
					$count['tests'] += substr_count( $contents, 'it(' );
					// Count non-empty lines of code in the test spec file.
					$count['tests_loc'] += substr_count( trim( $contents ), "\n" ) + 1; // +1 for the first line if file is not empty
				}
			}
		}

		return $count;
	};

	match ( $info['framework'] ) {
		'Codeception' => $info['tests'] = $count_Codeception_tests( $it ),
		'Playwright' => $info['tests'] = $count_Playwright_tests( $it ),
		'Puppeteer' => $info['tests'] = $count_Playwright_tests( $it ),
		default => $info['tests'] = 0,
	};

	return $info;
}

function get_unit_framework_info( string $path ): array {
	$info = [
		'framework' => '',
	];

	$find_framework = static function ( string $path ) {
		$suiteFiles = glob( dirname( $path ) . '/*.suite.yml' );
		if ( ! empty( $suiteFiles ) ) {
			return 'Codeception';
		} else {
			return 'PHPUnit';
		}
	};

	$info['framework'] = $find_framework( $path );

	$it = new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS );
	$it = new RecursiveIteratorIterator( $it );

	$count_Codeception_tests = static function ( RecursiveIteratorIterator $it ): array {
		$count = [
			'tests'     => 0,
			'tests_loc' => 0
		];

		foreach ( $it as $file ) {
			if ( $file->isFile() && $file->getExtension() === 'php' ) {
				// Check if it's a Codeception test file, which may contain Cest or Test in the filename
				$contents = file_get_contents( $file->getPathname() );
				// Use a regex to count the test methods. Look for both PascalCase and snake_case with @test annotation
				$count['tests'] += preg_match_all( '/public function (test[A-Z]\w+|test_[a-z_]\w*)\(/', $contents, $matches );
				$count['tests'] += preg_match_all( '/\*\s+@test\b/', $contents, $matches );
				// Count all non-empty lines of code in the test file
				$count['tests_loc'] += substr_count( trim( $contents ), "\n" ) + 1; // +1 for the first line if file is not empty
			}
		}

		return $count;
	};


	$count_PHPUnit_tests = static function ( RecursiveIteratorIterator $it ): array {
		$count = [
			'tests'     => 0,
			'tests_loc' => 0
		];

		foreach ( $it as $file ) {
			if ( $file->isFile() && $file->getExtension() === 'php' ) {
				$contents = file_get_contents( $file->getPathname() );
				// Count PHPUnit test methods
				$count['tests']     += preg_match_all( '/@test\b/', $contents, $matches );
				$count['tests']     += preg_match_all( '/function test\w+/', $contents, $matches );
				$count['tests_loc'] += substr_count( trim( $contents ), "\n" ) + 1;
			}
		}

		return $count;
	};

	match ( $info['framework'] ) {
		'Codeception' => $info['tests'] = $count_Codeception_tests( $it ),
		'PHPUnit' => $info['tests'] = $count_PHPUnit_tests( $it ),
		default => $info['tests'] = 0,
	};

	return $info;
}