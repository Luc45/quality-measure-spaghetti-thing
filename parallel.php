<?php

$parallelCount = $argv[1] ?? 10;

/** @var SplFileInfo $fileInfo */
foreach ( new DirectoryIterator( __DIR__ ) as $fileInfo ) {
	if ( $fileInfo->getExtension() === 'csv' && $fileInfo->getBasename() !== 'input.csv' ) {
		unlink( $fileInfo->getPathname() );
	}
}

$allRepos = [];

foreach ( new DirectoryIterator( __DIR__ . '/repos' ) as $fileInfo ) {
	if ( $fileInfo->isDir() && ! $fileInfo->isDot() ) {
		$allRepos[] = $fileInfo->getBasename();
	}
}

// Split repos into chunks for parallel processing
$reposPerParallel = array_chunk( $allRepos, ceil( count( $allRepos ) / $parallelCount ) );

$childPids = [];

for ( $i = 0; $i < $parallelCount; $i ++ ) {
	$pid = pcntl_fork();
	if ( $pid == - 1 ) {
		die( "Could not fork" );
	} elseif ( $pid ) {
		// Parent process
		$childPids[] = $pid;
	} else {
		// Child process
		$reposList     = implode( ',', $reposPerParallel[ $i ] ?? [] );
		$parallelIndex = $i + 1;
		exec( "php -d memory_limit=24G -dxdebug.start_with_request=yes calculate-complexity.php '$reposList' $parallelIndex" );
		exit( 0 ); // Terminate the child process
	}
}

// Wait for all child processes to finish
foreach ( $childPids as $childPid ) {
	pcntl_waitpid( $childPid, $status );
}

function combineCsvFiles( $headerFile, $outputFile, $chunks ) {
	// Open the headers file and write to the output file
	if ( ( $headers = file_get_contents( $headerFile ) ) !== false ) {
		file_put_contents( $outputFile, $headers );
	} else {
		throw new RuntimeException( "Unable to open headers file: $headerFile" );
	}

	// Append each chunk file to the output file
	foreach ( $chunks as $chunkFile ) {
		if ( file_exists( $chunkFile ) ) {
			$data = file_get_contents( $chunkFile );
			file_put_contents( $outputFile, $data, FILE_APPEND );
			unlink( $chunkFile ); // Delete the chunk file
		}
	}
}

// Combine human readable files
$humanChunks = glob( __DIR__ . '/human-[0-9]*.csv' );
combineCsvFiles( __DIR__ . '/human-headers.csv', __DIR__ . '/human.csv', $humanChunks );
unlink( __DIR__ . '/human-headers.csv' );

// Combine machine readable files
$machineChunks = glob( __DIR__ . '/machine-[0-9]*.csv' );
combineCsvFiles( __DIR__ . '/machine-headers.csv', __DIR__ . '/machine.csv', $machineChunks );
unlink( __DIR__ . '/machine-headers.csv' );

// Combine machine-small readable files
$machineChunks = glob( __DIR__ . '/machine-small-[0-9]*.csv' );
combineCsvFiles( __DIR__ . '/machine-small-headers.csv', __DIR__ . '/machine-small.csv', $machineChunks );
unlink( __DIR__ . '/machine-small-headers.csv' );

// Generate correlation heatmap
chdir( __DIR__ . '/python' );
exec( __DIR__ . '/python/spaghetti/bin/python' . " " . escapeshellarg( __DIR__ . '/python/correlation-heatmap.py' ), $output, $return_var );
chdir( __DIR__ );
