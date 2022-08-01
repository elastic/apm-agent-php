import * as semver from "https://deno.land/std/semver/mod.ts";

async function main() {
    let releases = await getReleases();

    const ghConfig = {
        'fail-fast': false,
        matrix: {
            include: [] as any
        }
    };

    // Build
    for (let release of releases) {
        for (let tag of release.tags) {
            ghConfig.matrix.include.push({
                name: `Shopware ${tag}`,
                runs: {
                    build: `docker buildx build --platform linux/amd64,linux/arm64 --build-arg SHOPWARE_DL=${release.download} --build-arg SHOPWARE_VERSION=${release.version} --tag ghcr.io/shyim/shopware:${tag} --tag shyim/shopware:${tag} --push .`
                }
            });
        }
    }

    await Deno.stdout.write(new TextEncoder().encode(JSON.stringify(ghConfig)));
}

function getMajorVersion(version: string) {
    let majorVersion = /\d+\.\d+/gm.exec(version);

    if (majorVersion && majorVersion[0]) {
        return majorVersion[0];
    } 

    return '';
}

main();

async function getReleases() {
    let json = await (await fetch('https://update-api.shopware.com/v1/releases/install?major=6')).json();
    let releases = [];
    let givenTags: string[] = [];


    for (let release of json) {
        try {
            if (semver.lt(release.version, '6.2.0')) {
                continue;
            }
        } catch (e) {
        }

        const majorVersion = getMajorVersion(release.version);

        let image = {
            version: release.version,
            download: release.uri,
            tags: [release.version]
        }

        if (!givenTags.includes(majorVersion)) {
            image.tags.push(majorVersion);
            givenTags.push(majorVersion);
        }

        if (!givenTags.includes('latest')) {
            image.tags.push('latest');
            givenTags.push('latest');
        }

        releases.push(image);
    }

    return releases;
}
