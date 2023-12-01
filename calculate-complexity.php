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

	evaluate_complexity_score();
	evaluate_maintenability_score();

	// Remove rows that do not need to be in the output CSV.
	foreach ( $GLOBALS['csvData'] as &$row ) {
		unset(
			$row['PluginDir'],
			$row['RepoDir'],
			$row['WPORG URL'],
			$row['Maintainer'],
			$row['Repo'],
			$row['WOOCOM URL'],
			$row['Dependency Injection'],
			$row['WOOCOM Installations'],
			$row['(php) New Lines per Month (Capped at 100)'],
			$row['(php) Changed Lines per Month (Capped at 100)'],
			$row['(php) New Lines per Month (99th percentile)'],
			$row['(php) New Lines per Month (Capped at 2500)'],
			$row['(php) Changed Lines per Month (Capped at 2500)'],
			$row['(php) New Lines per Month (Capped at 1000)'],
			$row['(php) New Lines per Month'],
			$row['(php) Changed Lines per Month'],
			$row['Public Functions'],
			$row['Protected/Private Functions'],
			$row['Static Functions'],
			$row['Slug'],
			$row['PHP File List'],
			$row['PHP Activity Over Time'],
			$row['RelativePluginDir'],
			$row['HerculesWhitelist'],
		);

		// Make all graph columns same size for consistency.
		foreach ( $row as $col_name => $col_value ) {
			if ( str_contains( $col_value, 'SPARKLINE' ) ) {
				unset( $row[ $col_name ] );
				$row[ str_pad( $col_name, 100, ' ', STR_PAD_RIGHT ) ] = $col_value;
			}
		}
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
		$data[ $key ] = array_merge( $data[ $key ], $row );
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
init();
evaluate_tests();
evaluate_support();
evaluate_php_loc();
evaluate_phpmd();
evaluate_phpcs();
evaluate_qit();
correlate_tests_and_code_locs();
add_aggregated_rating();
evaluate_bus_factor();
evaluate_change_concentration();
evaluate_php_activity_hercules();

function evaluate_complexity_score() {
	/**
	 * Available array keys in $row for calculating complexity factor:
	 * Extension    Aggregated Rating    WPORG Rating    WOOCOM Rating    WPORG Rating Count    WOOCOM Rating Count    WPORG Installations    Playwright Tests    Playwright LOC    wp-browser E2E Tests    wp-browser E2E LOC    Puppeteer E2E Tests    Puppeteer E2E LOC    wp-browser Unit Tests    wp-browser Unit LOC    PHPUnit Tests    PHPUnit LOC    Code Style Tests    QIT Integration    Autoloader    WPORG Supp    Resolved    PHP LOC    PHP Files    Static %    Encapsulated    self::/static::    Require/Include    Class Injections    Avg Methods per Classes    Total Method LOC    Average Method LOC    Longest Method LOC    Total Class LOC    Average Class LOC    Longest Class LOC    OOP LOCs    Total Cyclomatic Complexity    Average Cyclomatic Complexity    Biggest Cyclomatic Complexity    Unit Tests to PHP LOC    Unit Tests to OOP LOC    E2E Tests to PHP LOC    E2E Tests to OOP LOC    Tests to PHP LOC    Tests to OOP LOC    BusFactor    Top Changed PHP Files    Change Concentration    (php) Changed Lines per Month (99th percentile)                                                        (php) Changed Lines per Month (Capped at 2500)
	 */
	// Initialize variables for dynamic baselines and ceilings
	$minTotal   = PHP_INT_MAX;
	$maxTotal   = PHP_INT_MIN;
	$minAverage = PHP_INT_MAX;
	$maxAverage = PHP_INT_MIN;

	// First iteration to find min and max values
	foreach ( $GLOBALS['csvData'] as $row ) {
		$minTotal   = min( $minTotal, $row['Total Cyclomatic Complexity'] );
		$maxTotal   = max( $maxTotal, $row['Total Cyclomatic Complexity'] );
		$minAverage = min( $minAverage, $row['Average Cyclomatic Complexity'] );
		$maxAverage = max( $maxAverage, $row['Average Cyclomatic Complexity'] );
	}

	// Second iteration to calculate complexity scores
	foreach ( $GLOBALS['csvData'] as &$row ) {
		if ( isset( $row['Total Cyclomatic Complexity'], $row['Average Cyclomatic Complexity'] ) &&
		     is_numeric( $row['Total Cyclomatic Complexity'] ) &&
		     is_numeric( $row['Average Cyclomatic Complexity'] ) ) {

			// Normalize the complexity values within the dynamically determined ranges
			$normalizedTotal   = max( 0, min( 100, ( $row['Total Cyclomatic Complexity'] - $minTotal ) / ( $maxTotal - $minTotal ) * 100 ) );
			$normalizedAverage = max( 0, min( 100, ( $row['Average Cyclomatic Complexity'] - $minAverage ) / ( $maxAverage - $minAverage ) * 100 ) );

			// Calculate the weighted average of the normalized complexities
			$row['Complexity Score'] = ( $normalizedTotal * 0.4 + $normalizedAverage * 0.6 );
		} else {
			$row['Complexity Score'] = 0;  // Default score if data is missing or not numeric
		}
	}
}

function evaluate_maintenability_score() {
	/**
	 * Available array keys in $row for calculating maintenability score:
	 * Extension,Aggregated Rating,WPORG Rating,WOOCOM Rating,WPORG Rating Count,WOOCOM Rating Count,WPORG Installations,Playwright Tests,Playwright LOC,wp-browser E2E Tests,wp-browser E2E LOC,Puppeteer E2E Tests,Puppeteer E2E LOC,wp-browser Unit Tests,wp-browser Unit LOC,PHPUnit Tests,PHPUnit LOC,Code Style Tests,QIT Integration,Autoloader,WPORG Supp,Resolved,PHP LOC,PHP Files,self::/static::,Require/Include,Class Injections,Encapsulated,Static %,Avg Methods per Classes,Total Method LOC,Average Method LOC,Longest Method LOC,Total Class LOC,Average Class LOC,Longest Class LOC,OOP LOCs,Total Cyclomatic Complexity,Average Cyclomatic Complexity,Biggest Cyclomatic Complexity,Unit Tests to PHP LOC,Unit Tests to OOP LOC,E2E Tests to PHP LOC,E2E Tests to OOP LOC,Tests to PHP LOC,Tests to OOP LOC,BusFactor,Top Changed PHP Files,Change Concentration,Complexity Score,(php) Changed Lines per Month (99th percentile)                                                     ,(php) Changed Lines per Month (Capped at 1000)
	 */

	// First iteration to find min and max values
	foreach ( $GLOBALS['csvData'] as $row ) {
		$row['Maintenability Score'] = '';
	}
}

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
		'/repos/woocommerce/'             => 'plugins/woocommerce',
		'/repos/compatibility-dashboard/' => 'plugins/cd-manager',
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

function init() {
	foreach ( $GLOBALS['csvData'] as &$row ) {
		$repo_dir   = $row['RepoDir'];
		$plugin_dir = $row['PluginDir'];

		if ( $repo_dir !== $plugin_dir ) {
			$whitelist                = trim( str_replace( $repo_dir, '', $plugin_dir ), '/' );
			$row['RelativePluginDir'] = $whitelist;
			$row['HerculesWhitelist'] = "--whitelist \"$whitelist/*\"";
		} else {
			$row['RelativePluginDir'] = '';
			$row['HerculesWhitelist'] = '';
		}
	}
}

function report_progress( $action ) {
	static $current_action = null;
	static $progress = 0;
	$total = count( $GLOBALS['csvData'] );

	if ( $action !== $current_action ) {
		$progress       = 0;
		$current_action = $action;
	} else {
		$progress ++;
	}

	$processing_slug = array_values( $GLOBALS['csvData'] )[ $progress ]['Slug'];

	// Prepare the progress message
	$progress_message = "$action: $progress/$total [Processing $processing_slug]";

	// Print the message with enough padding to clear the line
	echo "\r" . $progress_message . str_repeat( ' ', max( 0, 80 - strlen( $progress_message ) ) );
	flush(); // Force the output to be written out
}

function evaluate_tests() {
	foreach ( $GLOBALS['csvData'] as &$row ) {
		report_progress( 'Evaluating tests' );

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
				'php',
			]
		];

		$overrides = [
			'woocommerce'            => [
				'e2e' => 'e2e-pw',
			],
			'woocommerce-pre-orders' => [
				'unit' => 'includes',
			],
		];

		if ( file_exists( $plugin_dir . '/tests' ) ) {
			$it = new DirectoryIterator( $plugin_dir . '/tests' );

			$debug['test_types'] = [
				'known'   => [],
				'unknown' => [],
			];

			$found_unit_tests = false;
			$has_override     = [];

			foreach ( $it as $i ) {
				$is_known = false;

				/** @var SplFileInfo $i */
				if ( $i->isDir() && ! $i->isDot() ) {
					if ( array_key_exists( $row['Slug'], $overrides ) ) {
						foreach ( $overrides[ $row['Slug'] ] as $type => $override ) {
							if ( $i->getBasename() === $override ) {
								$fn = "get_{$type}_framework_info";
								$fn( $i->getPathname(), $row );

								if ( $type === 'unit' ) {
									$found_unit_tests = true;
								}

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

							if ( $type === 'unit' ) {
								$found_unit_tests = true;
							}

							$debug['test_types']['known'][ $type ]['basename'] = $i->getBasename();
							$debug['test_types']['known'][ $type ]['path']     = $i->getPathname();
						}
					}

					if ( ! $is_known ) {
						$debug['test_types']['unknown'][] = $i->getBasename();
					}
				}
			}

			if ( ! $found_unit_tests ) {
				/** @var SplFileInfo $i */
				foreach ( $it as $i ) {
					if ( $i->isFile() && $i->getExtension() === 'php' ) {
						$contents = file_get_contents( $i->getPathname() );
						if ( preg_match( '/test/i', $contents ) ) {
							$unit_tests_dir = dirname( $i->getPathname() );
							$fn             = "get_unit_framework_info";
							$fn( $unit_tests_dir, $row );
							$debug['test_types']['known']['unit']['basename'] = basename( $unit_tests_dir );
							$debug['test_types']['known']['unit']['path']     = $unit_tests_dir;
							break;
						}
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

			$row['wp-browser E2E Tests'] = $t['cests'] + $t['scenarios'];
			$row['wp-browser E2E LOC']   = $t['cests_loc'] + $t['scenarios_loc'];
			break;
		case 'Playwright':
			$t = $count_Playwright_tests( $it );

			$row['Playwright Tests'] = $t['tests'];
			$row['Playwright LOC']   = $t['tests_loc'];
			break;
		case 'Puppeteer':
			$t = $count_Playwright_tests( $it );

			$row['Puppeteer E2E Tests'] = $t['tests'];
			$row['Puppeteer E2E LOC']   = $t['tests_loc'];
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

		$row['wp-browser Unit Tests'] = $t['tests'];
		$row['wp-browser Unit LOC']   = $t['tests_loc'];
	} else {
		$t = $count_PHPUnit_tests( $it );

		$row['PHPUnit Tests'] = $t['tests'];
		$row['PHPUnit LOC']   = $t['tests_loc'];
	}
}

function evaluate_support() {
	foreach ( $GLOBALS['csvData'] as &$row ) {
		report_progress( 'Evaluating support' );

		if ( empty( $row['WPORG URL'] ) ) {
			$row['WPORG Supp'] = '';
			$row['Resolved']   = '';
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

		$row['WPORG Supp'] = $plugin_info['support_threads'] ?? '';
		$resolved_threads  = $plugin_info['support_threads_resolved'] ?? 0;
		$total_threads     = $plugin_info['support_threads'] ?? 0;

		// Calculate the percentage of resolved threads
		if ( $total_threads > 0 ) {
			$resolved_percentage = ( $resolved_threads / $total_threads ) * 100;
			// Format the resolved percentage with two decimal places
			$row['Resolved'] = round( $resolved_percentage, 2 ) . '%';
		} else {
			$row['Resolved'] = '';
		}
	}
}

function evaluate_php_loc() {
	foreach ( $GLOBALS['csvData'] as &$row ) {
		report_progress( 'Evaluating PHP LOC' );

		$plugin_dir = $row['PluginDir'];

		$it = new RecursiveDirectoryIterator( $plugin_dir, FilesystemIterator::SKIP_DOTS );
		$it = new RecursiveIteratorIterator( $it );

		$count_php_metrics = static function ( RecursiveIteratorIterator $it ) use ( $plugin_dir ): array {
			$metrics = [
				'self_static_usage' => 0,
				'has_autoloader'    => 'No',
				'requires'          => 0,
				'class_injections'  => 0,
				'num_php_files'     => 0,
				'php_file_list'     => [],
				'longest_method'    => 0,
			];

			$command = "find $plugin_dir -path '*/tests/*' -prune -o -name '*.php' -exec wc -l {} +";
			exec( $command, $loc_output );
			$metrics['loc'] = array_sum( array_filter( $loc_output, static function ( $line ) {
				return (int) preg_match( '/^\s*\d+\s+total$/', $line );
			} ) );

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

			/** @var SplFileInfo $file */
			foreach ( $it as $file ) {
				if ( $file->isFile() && $file->getExtension() === 'php' ) {
					if ( str_contains( $file->getPathname(), '/tests/' ) ) {
						continue;
					}
					$metrics['num_php_files'] ++;
					$metrics['php_file_list'][] = $file->getPathname();
					// Skip vendor code.
					foreach ( $GLOBALS['vendor_directories'] as $vendor_dir ) {
						if ( str_contains( $file->getPathname(), "/$vendor_dir/" ) ) {
							continue 2;
						}
					}

					// Count non-empty lines of code.
					$contents                     = file_get_contents( $file->getPathname() );
					$metrics['self_static_usage'] += substr_count( $contents, 'self::' );
					$metrics['self_static_usage'] += substr_count( $contents, 'static::' );

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

			return $metrics; // This should be outside the foreach loop over $it
		};

		$metrics = $count_php_metrics( $it );

		$autoload_overrides = [
			'woocommerce-square' => 'Composer',
		];

		if ( array_key_exists( $row['Slug'], $autoload_overrides ) && ! empty( $autoload_overrides[ $row['Slug'] ] ) ) {
			$metrics['has_autoloader'] = $autoload_overrides[ $row['Slug'] ];
		}

		// Assign the metrics to the respective row fields
		$row['PHP LOC']          = $metrics['loc'];
		$row['PHP Files']        = $metrics['num_php_files'];
		$row['PHP File List']    = $metrics['php_file_list'];
		$row['self::/static::']  = $metrics['self_static_usage'];
		$row['Autoloader']       = $metrics['has_autoloader'];
		$row['Require/Include']  = $metrics['requires'];
		$row['Class Injections'] = $metrics['class_injections'];
	}
	unset( $row ); // Unset the reference to prevent potential issues later
}

function evaluate_phpmd() {
	foreach ( $GLOBALS['csvData'] as &$row ) {
		report_progress( 'Evaluating PHPMD' );

		$plugin_dir = $row['PluginDir'];

		$phpmd_cache_file = __DIR__ . "/cache/phpmd_{$row['Slug']}.json";

		if ( file_exists( $phpmd_cache_file ) ) {
			$phpmd_cache_hit = true;
			$phpmd_cache     = json_decode( file_get_contents( $phpmd_cache_file ), true );
		} else {
			$phpmd_cache_hit = false;
			$phpmd_cache     = [
				'total_classes'                      => 0,
				'total_class_length'                 => 0,
				'longest_class_length'               => 0,
				'class_count_length'                 => 0,
				'total_method_length'                => 0,
				'longest_method_length'              => 0,
				'method_count_length'                => 0,
				'total_cyclomatic_complexity'        => 0,
				'biggest_cyclomatic_complexity'      => 0,
				'method_count_cyclomatic_complexity' => 0,
				'total_public_methods'               => 0,
				'total_protected_methods'            => 0,
				'total_private_methods'              => 0,
				'total_static_methods'               => 0,
			];
		}

		if ( ! $phpmd_cache_hit ) {
			$phpmd_command = "php -d memory_limit=24G " . __DIR__ . "/vendor/bin/phpmd $plugin_dir/** --suffixes php json --reportfile phpmd_output.json ruleset.xml --exclude **/tests/**,**/AI/data/**";
			exec( $phpmd_command );
			if ( file_exists( 'phpmd_output.json' ) ) {
				try {
					$phpmd_output = json_decode( file_get_contents( 'phpmd_output.json' ), true, 512, JSON_THROW_ON_ERROR );
				} catch ( \JsonException $e ) {
					unlink( 'phpmd_output.json' );
					// Probably out of memory.
					echo "Failed to parse phpmd_output.json for {$row['Slug']}\n";
					die( 1 );
				}

				foreach ( $phpmd_output['files'] as $file ) {
					foreach ( $file['violations'] as $violation ) {
						if ( $violation['rule'] == 'ExcessiveMethodLength' ) {
							if ( preg_match( '/has (\d+) lines of code/', $violation['description'], $matches ) ) {
								$length = (int) $matches[1];

								if ( $length > $violation['endLine'] - $violation['beginLine'] + 1 ) {
									throw new \LogicException( "Method length exceeds the number of lines for {$row['Slug']}" );
								}

								// Update total method length
								$phpmd_cache['total_method_length'] += $length;

								// Update the longest method length
								if ( $length > $phpmd_cache['longest_method_length'] ) {
									$phpmd_cache['longest_method_length'] = $length;
								}

								// Increment method count
								$phpmd_cache['method_count_length'] ++;

								if ( $phpmd_cache['total_method_length'] > $row['PHP LOC'] ) {
									throw new \LogicException( "Total method length exceeds PHP LOC for {$row['Slug']}" );
								}
							}
						}
						if ( $violation['rule'] == 'ExcessiveClassLength' ) {
							if ( preg_match( '/has (\d+) lines of code/', $violation['description'], $matches ) ) {
								$length = (int) $matches[1];

								if ( $length > $violation['endLine'] - $violation['beginLine'] + 1 ) {
									throw new \LogicException( "Class length exceeds the number of lines for {$row['Slug']}" );
								}

								// Update total method length
								$phpmd_cache['total_class_length'] += $length;

								// Update the longest method length
								if ( $length > $phpmd_cache['longest_class_length'] ) {
									$phpmd_cache['longest_class_length'] = $length;
								}

								// Increment method count
								$phpmd_cache['class_count_length'] ++;

								if ( $phpmd_cache['total_class_length'] > $row['PHP LOC'] ) {
									throw new \LogicException( "Total class length exceeds PHP LOC for {$row['Slug']}" );
								}
							}
						}
						if ( $violation['rule'] == 'CyclomaticComplexity' ) {
							// Extract complexity value from the description
							if ( preg_match( '/Cyclomatic Complexity of (\d+)/', $violation['description'], $matches ) ) {
								$complexity = (int) $matches[1];

								// Update total cyclomatic complexity
								$phpmd_cache['total_cyclomatic_complexity'] += $complexity;

								// Update biggest cyclomatic complexity
								if ( $complexity > $phpmd_cache['biggest_cyclomatic_complexity'] ) {
									$phpmd_cache['biggest_cyclomatic_complexity'] = $complexity;
								}

								// Increment method count
								$phpmd_cache['method_count_cyclomatic_complexity'] ++;
							}
						}
						if ( $violation['rule'] == 'MethodVisibilityCount' ) {
							// Extract visibility counts from the description
							if ( preg_match( '/has (\d+) public methods, (\d+) protected methods, and (\d+) private methods/', $violation['description'], $matches ) ) {
								$publicMethods    = (int) $matches[1];
								$protectedMethods = (int) $matches[2];
								$privateMethods   = (int) $matches[3];

								// Update total public, protected, and private methods
								$phpmd_cache['total_public_methods']    += $publicMethods;
								$phpmd_cache['total_protected_methods'] += $protectedMethods;
								$phpmd_cache['total_private_methods']   += $privateMethods;
								$phpmd_cache['total_classes'] ++;
							}
						}
						if ( $violation['rule'] == 'StaticMethodCount' ) {
							// Extract the static method count from the description
							if ( preg_match( '/has (\d+) static methods/', $violation['description'], $matches ) ) {
								$staticMethodsCount = (int) $matches[1];

								// Update total static methods
								$phpmd_cache['total_static_methods'] += $staticMethodsCount;
							}
						}
					}
				}
				unlink( 'phpmd_output.json' );
			}
			file_put_contents( $phpmd_cache_file, json_encode( $phpmd_cache ) );
		}

		/*
		 * Public/Protected/Private Methods
		 */
		$total_methods        = $phpmd_cache['total_public_methods'] + $phpmd_cache['total_protected_methods'] + $phpmd_cache['total_private_methods'];
		$encapsulated_methods = $phpmd_cache['total_protected_methods'] + $phpmd_cache['total_private_methods'];

		$row['Public Functions']            = $phpmd_cache['total_public_methods'];
		$row['Protected/Private Functions'] = $encapsulated_methods;

		// Calculate the encapsulation percentage (public vs non-public)
		$row['Encapsulated'] = intval( $total_methods > 0 ? ( $encapsulated_methods / $total_methods ) * 100 : 0 ) . '%';

		/*
		 * Static Methods
		 */
		$static_percentage = $phpmd_cache['total_static_methods'] > 0 ? $phpmd_cache['total_static_methods'] / $total_methods * 100 : 0;

		$average_methods_per_class = $phpmd_cache['total_classes'] > 0 ? $total_methods / $phpmd_cache['total_classes'] : 0;

		$row['Static Functions']        = $phpmd_cache['total_static_methods'];
		$row['Static %']                = intval( $static_percentage ) . '%';
		$row['Avg Methods per Classes'] = number_format( $average_methods_per_class, 2 );

		/*
		 * Methods LOC.
		 */
		$phpmd_cache['average_method_length'] = $phpmd_cache['method_count_length'] > 0
			? $phpmd_cache['total_method_length'] / $phpmd_cache['method_count_length']
			: 0;

		// Assign PHPMD metrics to the respective row fields
		$row['Total Method LOC']   = $phpmd_cache['total_method_length'];
		$row['Average Method LOC'] = number_format( $phpmd_cache['average_method_length'], 2 );
		$row['Longest Method LOC'] = $phpmd_cache['longest_method_length'];

		// Calculate average class length
		$phpmd_cache['average_class_length'] = $phpmd_cache['class_count_length'] > 0
			? $phpmd_cache['total_class_length'] / $phpmd_cache['class_count_length']
			: 0;

		// Assign PHPMD metrics to the respective row fields
		$row['Total Class LOC']   = $phpmd_cache['total_class_length'];
		$row['Average Class LOC'] = number_format( $phpmd_cache['average_class_length'], 2 );
		$row['Longest Class LOC'] = $phpmd_cache['longest_class_length'];

		// Calculate OOP percentage from $phpmd_cache['total_class_length'] and $row['PHP LOC']
		$row['OOP LOCs'] = $row['PHP LOC'] > 0
			? number_format( $phpmd_cache['total_class_length'] / $row['PHP LOC'] * 100, 2 ) . '%'
			: 0;

		/*
		 * Cyclomatic Complexity.
		 */
		$phpmd_cache['average_cyclomatic_complexity'] = $phpmd_cache['method_count_cyclomatic_complexity'] > 0
			? $phpmd_cache['total_cyclomatic_complexity'] / $phpmd_cache['method_count_cyclomatic_complexity']
			: 0;

		// Assign PHPMD metrics to the respective row fields
		$row['Total Cyclomatic Complexity']   = $phpmd_cache['total_cyclomatic_complexity'];
		$row['Average Cyclomatic Complexity'] = number_format( $phpmd_cache['average_cyclomatic_complexity'], 2 );
		$row['Biggest Cyclomatic Complexity'] = $phpmd_cache['biggest_cyclomatic_complexity'];
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
		report_progress( 'Evaluating PHPCS' );

		$plugin_dir = $row['PluginDir'];
		$repo_dir   = $row['RepoDir'];

		$hardcodes = [
			'mailpoet' => true,
		];

		$phpcs_config_files = [
			'.phpcs.xml',
			'phpcs.xml',
			'.phpcs.xml.dist',
			'phpcs.xml.dist',
		];

		$has_phpcs = array_key_exists( $row['Slug'], $hardcodes ) && $hardcodes[ $row['Slug'] ];

		// Plugin Dir.
		if ( ! $has_phpcs ) {
			foreach ( $phpcs_config_files as $phpcs_file ) {
				$phpcs_filepath = $plugin_dir . '/' . $phpcs_file;

				if ( file_exists( $phpcs_filepath ) ) {
					$has_phpcs = true;
					break;
				}
			}
		}

		if ( ! $has_phpcs ) {
			foreach ( $phpcs_config_files as $phpcs_file ) {
				$phpcs_filepath = $repo_dir . '/' . $phpcs_file;

				if ( file_exists( $phpcs_filepath ) ) {
					$has_phpcs = true;
					break;
				}
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
		$oop_loc        = (int) $row['Total Class LOC'] ?? 0;
		$code_loc       = (int) $row['PHP LOC'] ?? 0;
		$unit_test_locs = (int) $row['PHPUnit LOC'] + (int) $row['wp-browser Unit LOC'];
		$e2e_test_locs  = (int) $row['Playwright LOC'] + (int) $row['Puppeteer E2E LOC'] + (int) $row['wp-browser E2E LOC'];
		$tests_loc      = $unit_test_locs + $e2e_test_locs;

		// Calculate the proportions as percentages, using shorthand ternary operator to avoid division by zero
		$unit_proportion_percentage  = $code_loc > 0 ? ( $unit_test_locs / $code_loc ) * 100 : 0;
		$e2e_proportion_percentage   = $code_loc > 0 ? ( $e2e_test_locs / $code_loc ) * 100 : 0;
		$tests_proportion_percentage = $code_loc > 0 ? ( $tests_loc / $code_loc ) * 100 : 0;

		$unit_proportion_percentage_oop  = $code_loc > 0 ? ( $unit_test_locs / $oop_loc ) * 100 : 0;
		$e2e_proportion_percentage_oop   = $code_loc > 0 ? ( $e2e_test_locs / $oop_loc ) * 100 : 0;
		$tests_proportion_percentage_oop = $code_loc > 0 ? ( $tests_loc / $oop_loc ) * 100 : 0;

		// Optionally, add the proportions as percentages to the row itself if you want to keep track within the data set
		$row['Unit Tests to PHP LOC'] = (int) $unit_proportion_percentage . '%';
		$row['Unit Tests to OOP LOC'] = (int) $unit_proportion_percentage_oop . '%';

		$row['E2E Tests to PHP LOC'] = (int) $e2e_proportion_percentage . '%';
		$row['E2E Tests to OOP LOC'] = (int) $e2e_proportion_percentage_oop . '%';

		$row['Tests to PHP LOC'] = (int) $tests_proportion_percentage . '%';
		$row['Tests to OOP LOC'] = (int) $tests_proportion_percentage_oop . '%';
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

function evaluate_bus_factor() {
	$calculateBusFactor = function ( $ownershipData ): string {
		$contributors  = $ownershipData['names'];
		$contributions = $ownershipData['people'];

		// Calculate the total LOC for each contributor across all date ranges
		$totalLocPerContributor = array_map( 'array_sum', $contributions );

		// Calculate the total LOC for the entire project
		$totalLocProject = array_sum( $totalLocPerContributor );

		// Calculate the percentage of code ownership for each contributor
		$ownershipPercentages = array_map( function ( $loc ) use ( $totalLocProject ) {
			return ceil( ( $loc / $totalLocProject ) * 100 );
		}, $totalLocPerContributor );

		// Sort the contributors by percentage in descending order
		arsort( $ownershipPercentages );

		// Get the top three contributors
		$topThreeContributors = array_slice( $ownershipPercentages, 0, 3, true );

		// Create an array of strings with the name and percentage
		$topContributorsWithNames = [];
		foreach ( $topThreeContributors as $index => $percentage ) {
			$name                       = substr( $contributors[ $index ], 0, 10 ); // Trim the name to 10 characters
			$topContributorsWithNames[] = $percentage . '% ' . $name;
		}

		// Return a scalar string with each contributor on a new line
		return implode( "\n", $topContributorsWithNames );
	};

	foreach ( $GLOBALS['csvData'] as &$row ) {
		report_progress( 'Evaluating Bus Factor' );

		$repo_dir = $row['RepoDir'];

		$cache_dir      = __DIR__ . '/cache/';
		$cache_filename = 'hercules-ownership-' . $row['Slug'] . '.json';
		$cache_filepath = $cache_dir . $cache_filename;

		/*
		 * See if cache exists.
		 * If yes, use cached json.
		 * If not, run a command based on this:
		 *  docker run --rm -v $(pwd):/repo --user $(id -u):$(id -g) srcd/hercules hercules --burndown --burndown-people --first-parent --devs --languages php /repo | docker run --rm -i -v $(pwd):/io --user $(id -u):$(id -g) srcd/hercules labours -m ownership -o /io/results-php.svg
		 * Cache the JSON
		 * Run $calculateBusFactor on the JSON
		 * Add it to the row as BusFactor
		 */
		if ( ! file_exists( $cache_filepath ) ) {
			// Navigate to the repository directory.
			chdir( $repo_dir );

			// Define the hercules command
			$herculesCommand = "docker run --env MPLCONFIGDIR=/cache/matplotlib --rm -v $(pwd):/repo --user $(id -u):$(id -g) srcd/hercules hercules --burndown --burndown-people --first-parent --devs /repo {$row['HerculesWhitelist']} | docker run --env MPLCONFIGDIR=/cache/matplotlib --rm -i -v $cache_dir:/cache --user $(id -u):$(id -g) srcd/hercules labours -m ownership -o /cache/$cache_filename";

			echo $herculesCommand . "\n";

			// Execute the hercules command and redirect output to a file
			exec( $herculesCommand, $output, $return_var );

			if ( $return_var !== 0 ) {
				echo "Hercules command failed with error code: {$return_var}\n";
				die( 1 );
			}
		}

		$ownershipData    = json_decode( file_get_contents( $cache_filepath ), true );
		$row['BusFactor'] = $calculateBusFactor( $ownershipData );
	}
}

function evaluate_php_activity_hercules() {
	$languages = [
		'php',
		//'javascript'
	];

	foreach ( $languages as $lang ) {
		$longest_graphs = [];
		foreach ( $GLOBALS['csvData'] as &$row ) {
			report_progress( "Evaluating $lang Activity" );

			$repo_dir = $row['RepoDir'];

			$yml_cache_dir      = __DIR__ . '/cache/hercules/';
			$yml_cache_filename = "hercules-devs-$lang-{$row['Slug']}.yml";
			$yml_cache_filepath = $yml_cache_dir . $yml_cache_filename;

			/*
			 * See if cache exists.
			 * If yes, use cached json.
			 * If not, run a command based on this:
			 *  docker run --rm -v $(pwd):/repo --user $(id -u):$(id -g) srcd/hercules hercules --burndown --burndown-people --first-parent --devs --languages php /repo | docker run --rm -i -v $(pwd):/io --user $(id -u):$(id -g) srcd/hercules labours -m ownership -o /io/results-php.svg
			 * Cache the JSON
			 * Run $calculateBusFactor on the JSON
			 * Add it to the row as BusFactor
			 */
			if ( ! file_exists( $yml_cache_filepath ) ) {
				// Navigate to the repository directory.
				chdir( $repo_dir );

				// Define the hercules command
				$herculesCommand = "docker run --env MPLCONFIGDIR=/cache/matplotlib --rm -v $(pwd):/repo --user $(id -u):$(id -g) srcd/hercules hercules --first-parent --devs --languages $lang /repo {$row['HerculesWhitelist']} > $yml_cache_filepath";

				echo $herculesCommand;

				// Execute the hercules command and redirect output to a file
				exec( $herculesCommand, $output, $return_var );

				if ( $return_var !== 0 ) {
					echo "Hercules command failed with error code: {$return_var}\n";
					die( 1 );
				}
			}

			$activity_cache_filename = sprintf( '%s%s-hpa.txt', $lang === 'javascript' ? 'js' : 'php', substr( $row['Slug'], 0, 55 ) );
			$activity_cache_dir      = __DIR__ . '/cache/activity/';
			$activity_cache_filepath = $activity_cache_dir . $activity_cache_filename;

			if ( ! file_exists( $activity_cache_filepath ) ) {
				$pythonEnvPath    = __DIR__ . '/hercules/python/mynewenv/bin/python';
				$pythonScriptPath = __DIR__ . '/hercules/python/labours';

				$pythonCommand = "$pythonEnvPath $pythonScriptPath -m old-vs-new -i $yml_cache_filepath > $activity_cache_filepath";

				// Execute the Python command
				exec( $pythonCommand, $output, $return_var );

				if ( $return_var !== 0 ) {
					echo "Python command failed with error code: {$return_var}\n";
					die( 1 );
				}
			}

			foreach ( file( $activity_cache_filepath ) as $line ) {
				if ( ! str_contains( $line, '||||' ) && ! str_contains( $line, 'SPARKLINE' ) ) {
					continue;
				}
				[ $columnName, $data ] = explode( '||||', $line, 2 );
				$row[ $columnName ] = trim( $data );

				if ( ! array_key_exists( $columnName, $longest_graphs ) ) {
					$longest_graphs[ $columnName ] = 0;
				}

				preg_match( '/=SPARKLINE\(\{([^}]+)\}/', $data, $matches );
				$length = count( explode( ',', $matches[1] ) );

				if ( $length > $longest_graphs[ $columnName ] ) {
					$longest_graphs[ $columnName ] = $length;
				}
			}
		}

		$development_activity = [];

		foreach ( $longest_graphs as $column => $maxLength ) {
			foreach ( $GLOBALS['csvData'] as &$row ) {
				if ( isset( $row[ $column ] ) ) {
					// Match the numerical data within the SPARKLINE structure
					if ( preg_match( '/=SPARKLINE\(\{([^}]+)\}(.*)/', $row[ $column ], $matches ) ) {
						$graphNumbers  = explode( ',', $matches[1] );

						$graphNumbers = array_map('intval', $graphNumbers);

						// Mailpoet moved their development directory, so let's take that into account.
						if ( $row['Slug'] === 'mailpoet' ) {
							// Ignore any value below 500.
							$graphNumbers = array_filter( $graphNumbers, static function ( $value ) {
								return $value > 500;
							} );
						}

						$currentLength = count( $graphNumbers );

						if ( ! array_key_exists( $row['Slug'], $development_activity ) ) {
							$development_activity[ $row['Slug'] ] = [];
						}

						if ( str_contains( $column, 'Changed Lines per Month (99th percentile)' ) ) {
							$development_activity[ $row['Slug'] ]["{$lang}_changed_lines"] = $graphNumbers;
						}

						if ( str_contains( $column, 'New Lines per Month (99th percentile)' ) ) {
							$development_activity[ $row['Slug'] ]["{$lang}_new_lines"] = $graphNumbers;
						}

						if ( $currentLength < $maxLength ) {
							// Pad the graph with zeros from the left
							$padding     = array_fill( 0, $maxLength - $currentLength, '0' );
							$paddedGraph = implode( ',', array_merge( $padding, $graphNumbers ) );
						} else {
							$paddedGraph = $matches[1];
						}

						unset( $row[ $column ] );

						// Replace the numerical data part of the SPARKLINE string
						$new_col = sprintf( '(%s) %s', $lang, $column );
						$new_val = '=SPARKLINE({' . $paddedGraph . '}' . $matches[2];

						if ( str_contains( $new_col, '(php) Changed Lines per Month (99th percentile)' ) ) {
							$new_val = str_replace( 'orange', 'green', $new_val );
						}

						$row[ $new_col ] = $new_val;
					}
				}
			}
			unset( $row ); // Unset the reference to the last element
		}

		foreach ( $development_activity as $slug => $data ) {
			$total_lines = [];

			foreach ( $data as $key => $values ) {
				// Extract language and type (new or changed)
				preg_match( '/^(.*)_([a-z]+_lines)$/', $key, $matches );
				$language = $matches[1];
				$type     = $matches[2];

				// Create a unique key for each language
				$totalKey = "{$language}_total_lines";

				if ( ! isset( $total_lines[ $totalKey ] ) ) {
					$total_lines[ $totalKey ] = array_fill( 0, count( $values ), 0 );
				}

				// Sum the values
				foreach ( $values as $index => $value ) {
					if ( isset( $total_lines[ $totalKey ][ $index ] ) ) {
						$total_lines[ $totalKey ][ $index ] += $value;
					} else {
						$total_lines[ $totalKey ][ $index ] = $value;
					}
				}
			}

			// Calculate development activity over the years
			foreach ( $total_lines as $languageKey => $lineCounts ) {
				// Convert the string numbers in lineCounts to integers
				$lineCountsInt = array_map( 'intval', $lineCounts );

				// Find the first non-zero month
				$startMonth = 0;
				foreach ( $lineCountsInt as $index => $value ) {
					if ( $value > 0 ) {
						$startMonth = $index;
						break;
					}
				}

				// Realign the array starting from the first non-zero month
				$realignedCounts = array_slice( $lineCountsInt, $startMonth );

				// Split the realigned array into chunks of 12 (each representing a year)
				$years = array_chunk( $realignedCounts, 12 );

				// Calculate the total of the first 12 months (or the first year's data available)
				$firstYearTotal = isset( $years[0] ) ? array_sum( $years[0] ) : 0;

				// Initialize an array to store activity percentages for each year
				$yearlyActivityPercentages = [];

				foreach ( $years as $year ) {
					// Calculate the total activity for each year
					$yearTotal = array_sum( $year );

					// Calculate the percentage relative to the first year total
					$percentage                  = $firstYearTotal ? ( $yearTotal / $firstYearTotal * 100 ) : 0;
					$yearlyActivityPercentages[] = intval( $percentage ) . '%';
				}

				// Calculate the average activity compared to the first year
				// Exclude the first year (100%) from the average calculation
				if ( count( $yearlyActivityPercentages ) > 1 ) {
					$sumPercentages = array_sum( array_slice( $yearlyActivityPercentages, 1 ) );
					$average        = $sumPercentages / ( count( $yearlyActivityPercentages ) - 1 );
				} else {
					// If there's only one year, set average to 100%
					$average = 100;
				}

				$langDisplay = $lang === 'php' ? 'PHP' : 'JS';

				// Store or use $yearlyActivityPercentages as needed
				$GLOBALS['csvData'][ $slug ][ $langDisplay . ' Activity Over Time' ]       = implode( ", ", $yearlyActivityPercentages );
				$GLOBALS['csvData'][ $slug ][ $langDisplay . ' Avg Compared to 1st Year' ] = "$average%";
			}
		}
	}
}

function evaluate_change_concentration() {
	foreach ( $GLOBALS['csvData'] as &$row ) {
		report_progress( 'Evaluating Change Concentration' );
		$repo_dir = $row['RepoDir'];

		$cache_file = __DIR__ . '/cache/git-change-concentration-' . $row['Slug'] . '.json';

		// Check if the cache file exists
		if ( file_exists( $cache_file ) ) {
			// Load the cached data
			$cached_data   = json_decode( file_get_contents( $cache_file ), true );
			$changed_files = $cached_data['changeConcentration'];
		} else {
			// Navigate to the repository directory.
			chdir( $repo_dir );

			// Get the list of PHP files and count changes
			$php_files = [];
			exec( "git ls-files | grep '{$row['RelativePluginDir']}/.*\.php$'", $php_files );
			$changes_per_file = [];

			foreach ( $php_files as $php_file ) {
				$change_count                  = exec( "git log --follow --oneline '{$php_file}' | wc -l" );
				$changes_per_file[ $php_file ] = (int) $change_count;
			}

			// Cache the results including total_php_commits
			file_put_contents( $cache_file, json_encode( [
				'changeConcentration' => $changes_per_file,
			] ) );

			$changed_files = $changes_per_file;
		}

		// Sort list of changed files by number of changes.
		arsort( $changed_files );

		// Calculate the change concentration
		$total_changes = array_sum( $changed_files );
		$hhi           = 0;

		if ( $total_changes > 0 ) {
			foreach ( $changed_files as $changes ) {
				$proportion = $changes / $total_changes;
				$hhi        += $proportion * $proportion;
			}
		}

		// Determine the number of top files to display (could be parameterized)
		$top_files_to_display = 3; // Example: showing top 10 files
		$most_files_changed   = array_slice( $changed_files, 0, $top_files_to_display, true );

		// Convert the array to a string, with each element on a new line for 'Most files changed'
		$most_changed_files = "";
		foreach ( $most_files_changed as $file => $changes ) {
			$most_changed_files .= substr( $changes . ' ' . str_replace( $row['RelativePluginDir'], '', $file ), 0, 35 ) . "\n";
		}

		$row['Top Changed PHP Files'] = trim( $most_changed_files );
		$row['Change Concentration']  = number_format( $hhi * 100, 2 ) . '%';
	}
}
