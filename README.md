# Uploader Service

This piece of software can be used to recursively scan a folder for large files and upload them to an AWS S3 bucket.
Optionally files can also be deleted after successfully uploaded.

# Installation

## As composer package

The software can be installed using [Composer](https://getcomposer.org) as dependency package.

```bash
composer require hadimodarres/uploader_service
```

The package will be installed in `vendor/hadimodarres/uploader_service`.

## Download

Alternative you can directly clone the repository or download it as a ZIP file.

```bash
git clone https://github.com/HadiModarres/uploader_service.git
```

After that you would still need to install all of it's dependencies.

```bash
cd uploader_service
composer install
```

# Usage

## Programmatic usage

```php
require_once 'vendor/autoload.php';

$config = ( new \UploaderService\Config\Config() )
    ->setPath( '/absolute/path/to/folder/to/be/processed' )
    ->setSizeThreshold( '1k' )
    ->setS3Region( 'eu-west-1' )
    ->setS3Bucket( 'your-bucket' )
    ->setS3Key( 'your-key' )
    ->setS3Secret( 'your-secret' )
    ->setDelete( true ); // pass false if you don't want files to be deleted

$uploader = new \UploaderService\Service\Uploader( $config );
$uploader->scan(); // scan files
$uploader->upload(); // upload scaned files
``` 

If you need to re-scan or re-upload files using the same configuration you can save on re-creating the uploader
instance. The only thing you **need** to do is call:

```php
$uploader->clear(); // clear queue of scanned files
```

### Console output

If you are running the above code in the terminal you might want to see some fancy output of what is going on.\
You can achieve this with the help of [Symfony Console](https://symfony.com/doc/current/components/console.html).

```php
$uploader = new \UploaderService\Service\Uploader(

    $config,
    new \UploaderService\Service\Uploader\Output( new \Symfony\Component\Console\Output\ConsoleOutput(), null, true )
    
); 
```

### Logging

You can also write logs.\
Just pass a `Psr\Log\LoggerInterface` as the second argument of the `UploaderService\Service\Uploader\Output` class.

Only the following 3 log levels are used:

 - `debug` - For trivial stuff like operation announcements and statistics.
 - `info` - For successful operations like file uploads and deletions.
 - `error` - For exceptions.

```php
$uploader = new \UploaderService\Service\Uploader(

    $config,
    new \UploaderService\Service\Uploader\Output(
    
        new \Symfony\Component\Console\Output\ConsoleOutput(),
        ( new \Monolog\Logger( 'upload' ) )
            ->pushHandler( new \Monolog\Handler\StreamHandler( 'debug.log', \Monolog\Logger::DEBUG ) ),
        true
        
    )
    
); 
```

## CLI usage

The package comes with a ready-to-use CLI executable located at `bin/cli.php`.\
You can run it with `php bin/cli.php upload ..` or even directly `./bin/cli.php upload ..`.

You can specify values for all of the configuration options right in the command line.\
For more info and available options run:

```bash
./bin/cli.php upload --help
```

# Custom CLI

If you need to use the package in the terminal, but don't want to use the provided `bin/cli.php` you can build your own
[Symfony Console](https://symfony.com/doc/current/components/console.html) compatible CLI.

```php
require_once 'vendor/autoload.php';

$app = new \Symfony\Component\Console\Application();

$app->add( new \UploaderService\Command\Upload() );

// or also pass config:
//
// $config = ( new \UploaderService\Config\Config() )
//     ->setPath( '/absolute/path/to/folder/to/be/processed' );
//
// $app->add( new \UploaderService\Command\Upload( $config ) );
 
$app->run();
```

The `\UploaderService\Command\Upload()` command will do an automatic merge of the configuration with options specified
via the command line. This means that you can pass incomplete config and have the rest configured via the terminal.

Keep in mind that options specified via terminal will override ones specified programmatically.
