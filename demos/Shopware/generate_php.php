<?php

$supportedVersions = ['7.4', '8.0', '8.1'];
$index = [];
$tpl = file_get_contents('Dockerfile.php.template');
$versionRegex ='/^(?<version>\d\.\d\.\d{1,})/m';

$workflow = <<<YML
name: Build PHP
on:
  workflow_dispatch:
  push:
    paths:
      - ".github/workflows/php.yml"
      - "rootfs/**"
jobs:
YML;

foreach ($supportedVersions as $supportedVersion)
{
    $apiResponse = json_decode(file_get_contents('https://hub.docker.com/v2/repositories/library/php/tags/?page_size=50&page=1&name=' . $supportedVersion. '.'), true);

    if (!is_array($apiResponse)) {
        throw new \RuntimeException("invalid api response");
    }

    $curVersion = null;
    $patchVersion = null;

    foreach ($apiResponse['results'] as $entry) {
        if (strpos($entry['name'], 'RC') !== false) {
            continue;
        }

        preg_match($versionRegex, $entry['name'], $patchVersion);

        if (count($patchVersion) > 0) {
            break;
        }
    }

    if ($patchVersion === null) {
        throw new \RuntimeException('There is no version found for PHP ' . $supportedVersion);
    }

    $folder = 'php/' . $supportedVersion . '/';
    if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
    }

    file_put_contents($folder . 'Dockerfile', str_replace('${PHP_VERSION}', $patchVersion['version'], $tpl));
    $index[$supportedVersion] = $patchVersion['version'];

    $workflowTpl = <<<'TPL'

  php%s:
    name: PHP %s
    runs-on: ubuntu-20.04
    steps:
      - uses: actions/checkout@v2
    
      - name: Login into Github Docker Registery
        run: echo "${{ secrets.GITHUB_TOKEN }}" | docker login ghcr.io -u ${{ github.actor }} --password-stdin

      - name: Set up QEMU
        uses: docker/setup-qemu-action@v1

      - name: Set up Docker Buildx
        id: buildx
        uses: docker/setup-buildx-action@v1

      - name: Build PHP
        run: docker buildx build -f ./%sDockerfile --platform linux/amd64,linux/arm64 --tag ghcr.io/shyim/shopware-php:%s --tag ghcr.io/shyim/shopware-php:%s --push .
TPL;

    $workflow .= sprintf($workflowTpl, str_replace('.', '', $supportedVersion), $supportedVersion, $folder, $supportedVersion, $patchVersion['version']);
}

file_put_contents('.github/workflows/php.yml', $workflow);
file_put_contents('index_php.json', json_encode($index, true, JSON_PRETTY_PRINT));
