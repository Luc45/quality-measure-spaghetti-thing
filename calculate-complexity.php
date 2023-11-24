<?php
$GLOBALS['csvData'] = [];

@unlink( __DIR__ . '/quality-vs-ratings-out.csv' );

$GLOBALS['vendor_directories'] = [
	'vendor',
	'node_modules',
	'vendor_prefixed',
];

register_shutdown_function( static function () {
	// Save the data to a CSV File.
	$csvFile = __DIR__ . '/quality-vs-ratings-out.csv';

	$fileHandle = fopen( $csvFile, 'w' );

	// Check if the file was opened successfully.
	if ( $fileHandle === false ) {
		throw new RuntimeException( sprintf( 'Unable to open file for writing: %s', $csvFile ) );
	}

	// Remove rows that do not need to be in the output CSV.
	foreach ( $GLOBALS['csvData'] as &$row ) {
		unset(
			$row['PluginDir'],
			$row['RepoDir'],
			$row['WPORG URL'],
			$row['Maintainer'],
			$row['WOOCOM URL'],
			$row['Dependency Injection'],
			$row['WOOCOM Installations'],
		);

		// Move to the end.
		$repo = $row['Repo'];
		unset( $row['Repo'] );
		$row['Repo'] = $repo;
	}

	$data = [];

	// Set the order of some columns.
	foreach ( $GLOBALS['csvData'] as $key => &$row ) {
		$data[ $key ]                      = [];
		$data[ $key ]['Extension']         = $row['﻿Extension'];
		$data[ $key ]['Aggregated Rating'] = $row['Aggregated Rating'];

		unset(
			$row['﻿Extension'],
			$row['Aggregated Rating']
		);
		$data[ $key ]                      = array_merge( $data[ $key ], $row );
	}

	// Add headers.
	fputcsv( $fileHandle, array_keys( $data[ array_rand( $data ) ] ) );

	// Iterate over the array to write each row to the CSV file.
	foreach ( $data as $foo => $r ) {
		// Check each item in $row to ensure it's scalar.
		foreach ( $r as $key => $value ) {
			if ( ! is_scalar( $value ) && ! is_null( $value ) ) {  // Allow scalars and NULL values.
				throw new InvalidArgumentException( sprintf( 'Non-scalar value encountered at key "%s": %s', $key, print_r( $value, true ) ) );
			}
		}

		fputcsv( $fileHandle, $r );
	}

	// Close the file handle.
	fclose( $fileHandle );
} );

load_csv();
maybe_download();
evaluate_tests();
evaluate_support();
evaluate_php_loc();
evaluate_phpcs();
evaluate_qit();
correlate_tests_and_code_locs();
add_aggregated_rating();

function load_csv() {
	$csvFile = __DIR__ . '/quality-vs-ratings.csv';

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

			$GLOBALS['csvData'][ $slug ] = array_combine( $headers, $row );
		}
		fclose( $handle );
	}
}

function maybe_download() {
	foreach ( $GLOBALS['csvData'] as &$row ) {
		$repo = $row['Repo'];
		$repo = rtrim( $repo, '/' );

		// Slug is the last part of the URL.
		$slug = explode( '/', $repo );
		$slug = end( $slug );

		// Make $repo_url, which converts "https://github.com/woocommerce/woocommerce-subscriptions" to "git@github.com:woocommerce/woocommerce-subscriptions.git"
		$repo_url = str_replace( 'https://github.com/', 'git@github.com:', $repo );
		$repo_url .= '.git';

		$project_dir = __DIR__ . "/repos/$slug";

		if ( ! file_exists( $project_dir ) ) {
			passthru( "git clone $repo_url $project_dir" );
			sleep( 1 );

			if ( ! file_exists( $project_dir ) ) {
				throw new Exception( "Failed to clone $repo_url" );
			}
		}

		$row['Slug']      = $slug;
		$row['RepoDir']   = $project_dir;
		$row['PluginDir'] = find_plugin_entrypoint( $project_dir );
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

function evaluate_tests() {
	foreach ( $GLOBALS['csvData'] as &$row ) {
		$plugin_dir         = $row['PluginDir'];
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

		$overrides = [
			'woocommerce'        => [
				'e2e'  => 'e2e-pw',
				'unit' => 'php',
			],
			'woocommerce-blocks' => [
				'unit' => 'php',
			]
		];

		if ( file_exists( $plugin_dir . '/tests' ) ) {
			$it = new DirectoryIterator( $plugin_dir . '/tests' );

			$debug['test_types'] = [
				'known'   => [],
				'unknown' => [],
			];

			$has_override = [];

			foreach ( $it as $i ) {
				$is_known = false;

				/** @var SplFileInfo $i */
				if ( $i->isDir() && ! $i->isDot() ) {
					if ( array_key_exists( $row['Slug'], $overrides ) ) {
						foreach ( $overrides[ $row['Slug'] ] as $type => $override ) {
							if ( $i->getBasename() === $override ) {
								$fn = "get_{$type}_framework_info";
								$fn( $i->getPathname(), $row );

								$debug['test_types']['known'][ $type ]['basename'] = $i->getBasename();
								$debug['test_types']['known'][ $type ]['path']     = $i->getPathname();

								$is_known       = true;
								$has_override[] = $type;
							}
						}
					}

					foreach ( $test_directories as $type => $possible_values ) {
						if ( in_array( $type, $has_override, true ) ) {
							continue;
						}
						if ( in_array( $i->getBasename(), $possible_values, true ) ) {
							$is_known = true;

							$fn = "get_{$type}_framework_info";
							$fn( $i->getPathname(), $row );

							$debug['test_types']['known'][ $type ]['basename'] = $i->getBasename();
							$debug['test_types']['known'][ $type ]['path']     = $i->getPathname();
						}
					}

					if ( ! $is_known ) {
						$debug['test_types']['unknown'][] = $i->getBasename();
					}
				}
			}
		}
	}
}

function get_e2e_framework_info( string $path, array &$row ) {
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
			if ( $fileExtension === 'php' && strpos( $file->getBasename(), 'Cest.php' ) !== false ) {
				return 'Codeception';
			}

			// Look for the playwright in JS and TS files
			if ( in_array( $fileExtension, [ 'js', 'ts' ] ) && strpos( $fileContent, 'playwright' ) !== false ) {
				return 'Playwright';
			}
		}
	};

	$framework = $find_framework( $path, $it );

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
			// Check for both 'js' and 'ts' extensions
			if ( $file->isFile() && ( $file->getExtension() === 'js' || $file->getExtension() === 'ts' ) ) {
				$filename = $file->getFilename();
				// Update regex to match files that end with .spec.js, .test.js, .spec.ts, or .test.ts
				if ( preg_match( '/\.(spec|test)\.(js|ts)$/i', $filename ) ) {
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

	switch ( $framework ) {
		case 'Codeception':
			$t = $count_Codeception_tests( $it );

			$row['wp-browser E2E Tests']     = $t['cests'] + $t['scenarios'];
			$row['wp-browser E2E Tests Loc'] = $t['cests_loc'] + $t['scenarios_loc'];
			break;
		case 'Playwright':
			$t = $count_Playwright_tests( $it );

			$row['Playwright Tests']     = $t['tests'];
			$row['Playwright Tests Loc'] = $t['tests_loc'];
			break;
		case 'Puppeteer':
			$t = $count_Playwright_tests( $it );

			$row['Puppeteer E2E Tests']     = $t['tests'];
			$row['Puppeteer E2E Tests LOC'] = $t['tests_loc'];
			break;
	}
}

function get_unit_framework_info( string $path, array &$row ) {
	$suiteFiles = glob( dirname( $path ) . '/*.suite.yml' );
	$framework  = empty( $suiteFiles ) ? 'PHPUnit' : 'Codeception';

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

	if ( $framework === 'Codeception' ) {
		$t = $count_Codeception_tests( $it );

		$row['wp-browser Unit Tests']     = $t['tests'];
		$row['wp-browser Unit Tests LOC'] = $t['tests_loc'];
	} else {
		$t = $count_PHPUnit_tests( $it );

		$row['PHPUnit Tests']     = $t['tests'];
		$row['PHPUnit Tests LOC'] = $t['tests_loc'];
	}
}

function evaluate_support() {
	foreach ( $GLOBALS['csvData'] as &$row ) {
		if ( empty( $row['WPORG URL'] ) ) {
			$row['WPORG Support Threads']          = '';
			$row['WPORG Support Threads Resolved'] = '';
			continue;
		}

		$slug = $row['Slug'];

		$slug_overrides = [
			'woocommerce-shipstation'     => 'woocommerce-shipstation-integration',
			'woocommerce-blocks'          => 'woo-gutenberg-products-block',
			'woocommerce-gateway-payfast' => 'woocommerce-payfast-gateway',
		];

		// Sometimes the WPORG slug is different from WOOCOM's.
		if ( array_key_exists( $slug, $slug_overrides ) ) {
			$slug = $slug_overrides[ $slug ];
		}

		$cache = __DIR__ . "/cache/wporg-$slug.json";

		if ( ! file_exists( $cache ) ) {
			sleep( 1 );
			$response = file_get_contents( "https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug=$slug" );
			if ( ! $response ) {
				throw new \RuntimeException( "Could not get plugin info for $slug" );
			}
			if ( ! file_put_contents( $cache, $response ) ) {
				throw new \RuntimeException( "Could not write plugin info for $slug" );
			}
		} else {
			$response = file_get_contents( $cache );
		}

		$plugin_info = json_decode( $response, true );

		$row['WPORG Support Threads'] = $plugin_info['support_threads'] ?? '';
		$resolved_threads             = $plugin_info['support_threads_resolved'] ?? 0;
		$total_threads                = $plugin_info['support_threads'] ?? 0;

		// Calculate the percentage of resolved threads
		if ( $total_threads > 0 ) {
			$resolved_percentage = ( $resolved_threads / $total_threads ) * 100;
			// Format the resolved percentage with two decimal places
			$row['WPORG Support Threads Resolved'] = round( $resolved_percentage, 2 ) . '%';
		} else {
			$row['WPORG Support Threads Resolved'] = '';
		}
	}
}

function evaluate_php_loc() {
	foreach ( $GLOBALS['csvData'] as &$row ) {
		$plugin_dir = $row['PluginDir'];

		$it = new RecursiveDirectoryIterator( $plugin_dir, FilesystemIterator::SKIP_DOTS );
		$it = new RecursiveIteratorIterator( $it );

		$count_php_metrics = static function ( RecursiveIteratorIterator $it ) use ( $plugin_dir ): array {
			$metrics = [
				'loc'                         => 0,
				'public_functions'            => 0,
				'protected_private_functions' => 0,
				'static_functions'            => 0,
				'self_static_usage'           => 0,
				'has_autoloader'              => 'No',
				'requires'                    => 0,
				'class_injections'            => 0,
				'num_php_files'               => 0,
				'num_classes'                 => 0,
			];

			// Check for composer.json with autoload.
			$composer_path = $plugin_dir . '/composer.json';
			if ( file_exists( $composer_path ) ) {
				$composer_config = json_decode( file_get_contents( $composer_path ), true );
				if ( ! empty( $composer_config['autoload'] ) ) {
					$metrics['has_autoloader'] = 'Composer';
				}
			}

			$spl_autoload_register = 0;

			$nonClassTypes        = [
				'int',
				'float',
				'string',
				'bool',
				'boolean',
				'array',
				'iterable',
				'callable',
				'mixed',
				'resource',
				'null',
				'void'
			];
			$patternNonClassTypes = implode( '|', $nonClassTypes );

			foreach ( $it as $file ) {
				if ( $file->isFile() && $file->getExtension() === 'php' ) {
					$metrics['num_php_files'] ++;
					// Skip vendor code.
					foreach ( $GLOBALS['vendor_directories'] as $vendor_dir ) {
						if ( str_contains( $file->getPathname(), "/$vendor_dir/" ) ) {
							continue 2;
						}
					}

					// Count non-empty lines of code.
					$contents                               = file_get_contents( $file->getPathname() );
					$metrics['loc']                         += substr_count( trim( $contents ), "\n" ) + 1;
					$metrics['public_functions']            += preg_match_all( '/\bpublic function\b/', $contents );
					$metrics['protected_private_functions'] += preg_match_all( '/\b(protected|private) function\b/', $contents );
					$metrics['static_functions']            += preg_match_all( '/\b(public|protected|private) static function\b/', $contents );
					$metrics['self_static_usage']           += substr_count( $contents, 'self::' );
					$metrics['self_static_usage']           += substr_count( $contents, 'static::' );
					$metrics['num_classes']                 += substr_count( $contents, 'class ' );

					// Count how many spl_autoload_register it has, and add it to "has_autoloader" like this: "1 spl_autoload_register"
					if ( $metrics['has_autoloader'] !== 'Composer' ) {
						$spl_autoload_register += substr_count( $contents, 'spl_autoload_register' );
					}

					// Tokenize the file contents and count the specific statements.
					$tokens = token_get_all( $contents );

					foreach ( $tokens as $token ) {
						if ( is_array( $token ) ) {
							switch ( $token[0] ) {
								case T_REQUIRE:
								case T_REQUIRE_ONCE:
								case T_INCLUDE:
								case T_INCLUDE_ONCE:
									$metrics['requires'] ++;
									break;
							}
						}
					}

					// Match all class constructors.
					preg_match_all( '/function\s+__construct\s*\(([^)]*)\)/', $contents, $constructors );

					foreach ( $constructors[1] as $constructor ) {
						// Break parameters by comma and filter out empty entries.
						$params = array_filter( array_map( 'trim', explode( ',', $constructor ) ) );

						foreach ( $params as $param ) {
							// Check if the parameter has a type hint and is not a non-class type.
							if ( preg_match( '/^(?:' . $patternNonClassTypes . ')\s+\$/', $param ) === 0 ) {
								$metrics['class_injections'] ++;
							}
						}
					}
				}
			}

			if ( $metrics['has_autoloader'] !== 'Composer' && $spl_autoload_register > 0 ) {
				$metrics['has_autoloader'] = "$spl_autoload_register spl_autoload_register";
			}

			// Calculate the percentage of static vs non-static functions
			$total_functions              = $metrics['public_functions'] + $metrics['protected_private_functions'];
			$metrics['static_percentage'] = $total_functions > 0 ? ( $metrics['static_functions'] / $total_functions ) * 100 : 0;

			// Calculate the encapsulation percentage (public vs non-public)
			$metrics['encapsulation_percentage'] = $total_functions > 0 ? ( $metrics['protected_private_functions'] / $total_functions ) * 100 : 0;

			$metrics['ratio_methods_and_classes'] = $metrics['num_classes'] > 0 ? number_format( $total_functions / $metrics['num_classes'], 2 ) : 0;

			return $metrics; // This should be outside the foreach loop over $it
		};

		$metrics = $count_php_metrics( $it );

		// Assign the metrics to the respective row fields
		$row['PHP LOC']                         = $metrics['loc'];
		$row['Public Functions']                = $metrics['public_functions'];
		$row['Protected/Private Functions']     = $metrics['protected_private_functions'];
		$row['Static Functions']                = $metrics['static_functions'];
		$row['Static vs Non-Static Percentage'] = intval( $metrics['static_percentage'] ) . '%';
		$row['Encapsulation Percentage']        = intval( $metrics['encapsulation_percentage'] ) . '%';
		$row['self::/static:: Usage']           = $metrics['self_static_usage'];
		$row['Autoloader']                      = $metrics['has_autoloader'];
		$row['Require/Include']                 = $metrics['requires'];
		$row['Class Injections']                = $metrics['class_injections'];
		$row['Ratio Methods and Classes']       = $metrics['ratio_methods_and_classes'];
	}
	unset( $row ); // Unset the reference to prevent potential issues later
}

function evaluate_phpcs() {
	/**
	 * Do a foreach on the csvData
	 * Open a non-recursive Directory Iterator
	 * Search for '.phpcs.xml', 'phpcs.xml', '.phpcs.xml.dist', 'phpcs.xml.dist'
	 * If it has it, set $row['Code Style Tests'] to 'Yes', or 'No'
	 */
	foreach ( $GLOBALS['csvData'] as &$row ) {
		$plugin_dir = $row['PluginDir'];

		$phpcs_config_files = [
			'.phpcs.xml',
			'phpcs.xml',
			'.phpcs.xml.dist',
			'phpcs.xml.dist',
		];

		$has_phpcs = false;

		foreach ( $phpcs_config_files as $phpcs_file ) {
			$phpcs_filepath = $plugin_dir . '/' . $phpcs_file;

			if ( file_exists( $phpcs_filepath ) ) {
				$has_phpcs = true;
				break;
			}
		}

		$row['Code Style Tests'] = $has_phpcs ? 'Yes' : 'No';
	}
}

function evaluate_qit() {
	/**
	 * Do a foreach on the csvData
	 * Open a non-recursive Directory Iterator on .github/workflows, if it exists
	 * If it doesn't exist, set $row['QIT in CI'] to 'No'
	 * It if exists, search for "qit:run" in all .yml files in that directory
	 * Do a regex searching for what test type it runs, eg: run:activation, run:api, run:e2e, run:phpcompatibility, etc
	 * Save the value of "QIT in CI" as a list of the test types it runs, separated by comma
	 */
	foreach ( $GLOBALS['csvData'] as &$row ) {
		$plugin_dir    = $row['PluginDir'];
		$workflows_dir = $plugin_dir . '/.github/workflows';
		$test_types    = [];

		if ( file_exists( $workflows_dir ) && is_dir( $workflows_dir ) ) {
			$it = new DirectoryIterator( $workflows_dir );
			foreach ( $it as $fileinfo ) {
				if ( $fileinfo->isFile() && $fileinfo->getExtension() === 'yml' ) {
					$content = file_get_contents( $fileinfo->getPathname() );
					// Regex to match 'qit run:<test_type>' pattern
					if ( preg_match_all( '/qit\s+run:(\w+)/', $content, $matches ) ) {
						$test_types = array_merge( $test_types, $matches[1] );
					}
				}
			}
		}

		if ( empty( $test_types ) ) {
			$row['QIT Integration'] = 'No';
		} else {
			$row['QIT Integration'] = implode( ',', array_unique( $test_types ) );
		}
	}
}

function correlate_tests_and_code_locs() {
	foreach ( $GLOBALS['csvData'] as &$row ) {
		// Use null coalescing operator to set default to 0 if not set
		$code_loc       = (int) $row['PHP LOC'] ?? 0;
		$unit_test_locs = (int) $row['PHPUnit Tests LOC'] + (int) $row['wp-browser Unit Tests LOC'];
		$e2e_test_locs  = (int) $row['Playwright Tests Loc'] + (int) $row['Puppeteer E2E Tests LOC'] + (int) $row['wp-browser E2E Tests Loc'];

		// Calculate the proportions as percentages, using shorthand ternary operator to avoid division by zero
		$unit_proportion_percentage = $code_loc > 0 ? ( $unit_test_locs / $code_loc ) * 100 : 0;
		$e2e_proportion_percentage  = $code_loc > 0 ? ( $e2e_test_locs / $code_loc ) * 100 : 0;

		// Optionally, add the proportions as percentages to the row itself if you want to keep track within the data set
		$row['Unit Tests to Code LOC'] = (int) $unit_proportion_percentage . '%';
		$row['E2E Tests to Code LOC']  = (int) $e2e_proportion_percentage . '%';
	}
}

function add_aggregated_rating() {
	foreach ( $GLOBALS['csvData'] as &$row ) {
		$wporg_rating  = (float) $row['WPORG Rating'];
		$woocom_rating = (float) $row['WOOCOM Rating'];

		// Check if both ratings are higher than zero
		if ( $wporg_rating > 0 && $woocom_rating > 0 ) {
			// Calculate the average of both ratings if both are set
			$row['Aggregated Rating'] = ( $wporg_rating + $woocom_rating ) / 2;
		} elseif ( $wporg_rating > 0 || $woocom_rating > 0 ) {
			// Use the one rating that is set if the other is zero
			$row['Aggregated Rating'] = max( $wporg_rating, $woocom_rating );
		} else {
			// Set to empty string if both ratings are zero
			$row['Aggregated Rating'] = '';
		}
	}
}