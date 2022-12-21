<?php

$phpMatrix = [
    '6.4.7.0' => ['7.4', '8.0', '8.1'],
    '6.4.1.2' => ['7.4', '8.0'],
    'default' => ['7.4'],
];
$phpIndex = json_decode(file_get_contents('index_php.json'), true);

$dockerTpl = file_get_contents('Dockerfile.template');
$shopwareVersions = json_decode(file_get_contents('https://update-api.shopware.com/v1/releases/install?major=6'), true);
$usedTags = [];


$workflow = <<<YML
name: Build Shopware
on:
  workflow_dispatch:
  push:
    paths:
      - ".github/workflows/shopware.yml"
      - "version.txt"
jobs:
YML;

foreach($shopwareVersions as $shopwareVersion) {
    // skip very old versions
    if (version_compare('6.2.0', $shopwareVersion['version'], '>')) {
        continue;
    }

    $versionTags = [];

    if (!isset($usedTags['latest'])) {
        $usedTags['latest'] = 1;
        $versionTags[] = 'latest';
    }

    preg_match('/^\d+\.\d+/', $shopwareVersion['version'], $majorVersion);
    preg_match('/^\d+\.\d+.\d+/', $shopwareVersion['version'], $minorVersion);

    if (!isset($usedTags[$majorVersion[0]])) {
        $versionTags[] = $majorVersion[0];
        $usedTags[$majorVersion[0]] = 1;
    }

    if (!isset($usedTags[$minorVersion[0]])) {
        $versionTags[] = $minorVersion[0];
        $usedTags[$minorVersion[0]] = 1;
    }

    if (!isset($usedTags[$shopwareVersion['version']])) {
        $versionTags[] = $shopwareVersion['version'];
    }

    $phpVersions = [];

    foreach($phpMatrix as $matrix => $versions) {
        if ($matrix === 'default' || version_compare($shopwareVersion['version'], $matrix, ">=")) {
            $phpVersions = $versions;
            break;
        }
    }

    foreach($phpVersions as $i => $php) {
        $folder = 'shopware/' . $php . '/' . $shopwareVersion['version'];

        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }

        $replacements = [
            '${PHP_VERSION}' => $phpIndex[$php] ?? $php,
            '${SHOPWARE_VERSION}' => $shopwareVersion['version'],
            '${SHOPWARE_DL}' => $shopwareVersion['uri'],
        ];

        file_put_contents($folder . '/Dockerfile', str_replace(array_keys($replacements), $replacements, $dockerTpl));

        $workflowTpl = <<<'TPL'

  #JOBKEY#:
    name: #NAME#
    runs-on: ubuntu-20.04
    steps:
      - uses: actions/checkout@v2
    
      - name: Login into Docker Hub Registery
        run: echo "${{ secrets.DOCKER_PASSWORD }}" | docker login -u "shyim" --password-stdin

      - name: Login into Github Docker Registery
        run: echo "${{ secrets.GITHUB_TOKEN }}" | docker login ghcr.io -u ${{ github.actor }} --password-stdin

      - name: Set up QEMU
        uses: docker/setup-qemu-action@v1

      - name: Set up Docker Buildx
        id: buildx
        uses: docker/setup-buildx-action@v1

      - name: Build PHP
        run: docker buildx build -f #DOCKER_FILE# --platform linux/amd64,linux/arm64 #TAGS# --push .
TPL;

    $tags = '';

    foreach($versionTags as $tag) {
        // default php version is always lowest
        if ($i === 0 ) {
            $tags .= '--tag ghcr.io/shyim/shopware:' . $tag . ' ';
            $tags .= '--tag shyim/shopware:' . $tag . ' ';
        }

        $tags .= '--tag ghcr.io/shyim/shopware:' . $tag . '-php' . $php . ' ';
        $tags .= '--tag shyim/shopware:' . $tag . '-php' . $php . ' ';

        if ($php !== $phpIndex[$php] ?? $php) {
            $tags .= '--tag ghcr.io/shyim/shopware:' . $tag . '-php' . $phpIndex[$php] . ' ';
            $tags .= '--tag shyim/shopware:' . $tag . '-php' . $phpIndex[$php] . ' ';
        }
    }

    $replacements = [
        '#JOBKEY#' => str_replace('.', '_', 'shopware-' . $shopwareVersion['version'] . '-' . $php),
        '#NAME#' => $shopwareVersion['version'] . ' with PHP ' . $php,
        '#TAGS#' => $tags,
        '#DOCKER_FILE#' => './' . $folder . '/Dockerfile'
    ];

    $workflow .= str_replace(array_keys($replacements), $replacements, $workflowTpl);
    }
}

file_put_contents('.github/workflows/shopware.yml', $workflow);