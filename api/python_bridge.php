<?php
/**
 * Python Bridge
 * 
 * Spawns a Python process to run the recommendation model.
 * Communicates via STDIN/STDOUT using JSON.
 */

require_once __DIR__ . '/../config.php';

/**
 * Calls the Python model with the given payload.
 * 
 * @param array $payload The data to send to the model (student_id, semester)
 * @return array The decoded JSON response from the model
 * @throws Exception If the process fails or output is invalid
 */
function call_python_model(array $payload): array {
    $scriptPath = PYTHON_MODEL_PATH;
    $pythonExec = PYTHON_EXECUTABLE;

    if (!file_exists($scriptPath)) {
        throw new Exception("Python model script not found at: $scriptPath");
    }

    $descriptorspec = [
        0 => ["pipe", "r"], // stdin
        1 => ["pipe", "w"], // stdout
        2 => ["pipe", "w"]  // stderr
    ];

    $cmd = escapeshellcmd($pythonExec) . " " . escapeshellarg($scriptPath);
    $process = proc_open($cmd, $descriptorspec, $pipes);

    if (!is_resource($process)) {
        throw new Exception("Failed to start Python process: $cmd");
    }

    // Send payload to STDIN
    fwrite($pipes[0], json_encode($payload));
    fclose($pipes[0]);

    // Read response from STDOUT
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    // Read errors from STDERR
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        throw new Exception("Python process exited with code $exitCode. Error: $stderr");
    }

    $result = json_decode($stdout, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to decode Python response. Raw output: $stdout. Stderr: $stderr");
    }

    return $result;
}
