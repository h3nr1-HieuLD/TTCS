<?php

namespace App\Services;

use Exception;

class BuildService
{
    private $buildDir;
    
    public function __construct()
    {
        $this->buildDir = storage_path('app/builds');
        if (!file_exists($this->buildDir)) {
            mkdir($this->buildDir, 0755, true);
        }
    }
    
    public function build(array $buildData)
    {
        $buildPath = $this->getBuildPath($buildData['build_id']);
        
        try {
            // Clone repository
            $this->cloneRepository($buildData['repository'], $buildPath);
            
            // Checkout specific commit
            $this->checkoutCommit($buildPath, $buildData['commit']);
            
            // Run build process
            $buildResult = $this->runBuildProcess($buildPath, $buildData);
            
            return [
                'build_id' => $buildData['build_id'],
                'status' => 'success',
                'repository' => $buildData['repository'],
                'commit' => $buildData['commit'],
                'result' => $buildResult,
                'timestamp' => time()
            ];
            
        } catch (Exception $e) {
            throw new Exception("Build failed: " . $e->getMessage());
        } finally {
            // Cleanup build directory
            $this->cleanup($buildPath);
        }
    }
    
    private function getBuildPath($buildId)
    {
        return $this->buildDir . '/' . $buildId;
    }
    
    private function cloneRepository($repository, $path)
    {
        $command = sprintf('git clone %s %s 2>&1', escapeshellarg($repository), escapeshellarg($path));
        $output = [];
        $returnVar = 0;
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("Failed to clone repository: " . implode("\n", $output));
        }
    }
    
    private function checkoutCommit($path, $commit)
    {
        $command = sprintf('cd %s && git checkout %s 2>&1', escapeshellarg($path), escapeshellarg($commit));
        $output = [];
        $returnVar = 0;
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("Failed to checkout commit: " . implode("\n", $output));
        }
    }
    
    private function runBuildProcess($path, $buildData)
    {
        // Detect project type and run appropriate build commands
        if (file_exists($path . '/package.json')) {
            return $this->buildNodeProject($path);
        } elseif (file_exists($path . '/composer.json')) {
            return $this->buildPHPProject($path);
        } else {
            throw new Exception("Unsupported project type");
        }
    }
    
    private function buildNodeProject($path)
    {
        $commands = [
            'npm install --production',
            'npm run build'
        ];
        
        return $this->executeCommands($path, $commands);
    }
    
    private function buildPHPProject($path)
    {
        $commands = [
            'composer install --no-dev --optimize-autoloader',
            'php artisan optimize'
        ];
        
        return $this->executeCommands($path, $commands);
    }
    
    private function executeCommands($path, array $commands)
    {
        $results = [];
        
        foreach ($commands as $command) {
            $fullCommand = sprintf('cd %s && %s 2>&1', escapeshellarg($path), $command);
            $output = [];
            $returnVar = 0;
            
            exec($fullCommand, $output, $returnVar);
            
            $results[] = [
                'command' => $command,
                'output' => implode("\n", $output),
                'success' => $returnVar === 0
            ];
            
            if ($returnVar !== 0) {
                throw new Exception("Command failed: " . $command . "\n" . implode("\n", $output));
            }
        }
        
        return $results;
    }
    
    private function cleanup($path)
    {
        if (file_exists($path)) {
            $command = sprintf('rm -rf %s', escapeshellarg($path));
            exec($command);
        }
    }
}