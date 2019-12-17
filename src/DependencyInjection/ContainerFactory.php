<?php declare(strict_types = 1);

namespace PHPStan\DependencyInjection;

use Nette\DI\Extensions\PhpExtension;
use Phar;
use PHPStan\Broker\Broker;
use PHPStan\File\FileHelper;
use PHPStan\File\FuzzyRelativePathHelper;
use PHPStan\Reflection\ReflectionProvider;

class ContainerFactory
{

	/** @var string */
	private $currentWorkingDirectory;

	/** @var string */
	private $rootDirectory;

	/** @var string */
	private $configDirectory;

	public function __construct(string $currentWorkingDirectory)
	{
		$this->currentWorkingDirectory = $currentWorkingDirectory;
		$fileHelper = new FileHelper($currentWorkingDirectory);

		$rootDir = __DIR__ . '/../..';
		$originalRootDir = $fileHelper->normalizePath($rootDir);
		if (extension_loaded('phar')) {
			$pharPath = Phar::running(false);
			if ($pharPath !== '') {
				$rootDir = dirname($pharPath);
			}
		}
		$this->rootDirectory = $fileHelper->normalizePath($rootDir);
		$this->configDirectory = $originalRootDir . '/conf';
	}

	/**
	 * @param string $tempDirectory
	 * @param string[] $additionalConfigFiles
	 * @param string[] $analysedPaths
	 * @return \PHPStan\DependencyInjection\Container
	 */
	public function create(
		string $tempDirectory,
		array $additionalConfigFiles,
		array $analysedPaths
	): Container
	{
		$configurator = new Configurator(new LoaderFactory(
			$this->rootDirectory,
			$this->currentWorkingDirectory
		));
		$configurator->defaultExtensions = [
			'php' => PhpExtension::class,
			'extensions' => \Nette\DI\Extensions\ExtensionsExtension::class,
		];
		$configurator->setDebugMode(true);
		$configurator->setTempDirectory($tempDirectory);
		$configurator->addParameters([
			'rootDir' => $this->rootDirectory,
			'currentWorkingDirectory' => $this->currentWorkingDirectory,
			'cliArgumentsVariablesRegistered' => ini_get('register_argc_argv') === '1',
			'tmpDir' => $tempDirectory,
		]);
		$configurator->addConfig($this->configDirectory . '/config.neon');
		foreach ($additionalConfigFiles as $additionalConfigFile) {
			$configurator->addConfig($additionalConfigFile);
		}

		$configurator->addServices([
			'relativePathHelper' => new FuzzyRelativePathHelper($this->currentWorkingDirectory, DIRECTORY_SEPARATOR, $analysedPaths),
		]);

		$container = $configurator->createContainer();

		/** @var ReflectionProvider $reflectionProvider */
		$reflectionProvider = $container->getByType(ReflectionProvider::class);
		Broker::registerInstance($reflectionProvider);
		$container->getService('typeSpecifier');

		return $container->getByType(Container::class);
	}

	public function getCurrentWorkingDirectory(): string
	{
		return $this->currentWorkingDirectory;
	}

	public function getRootDirectory(): string
	{
		return $this->rootDirectory;
	}

	public function getConfigDirectory(): string
	{
		return $this->configDirectory;
	}

}
