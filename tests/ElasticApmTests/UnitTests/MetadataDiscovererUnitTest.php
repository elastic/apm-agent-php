<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\Impl\Log\NoopLoggerFactory;
use Elastic\Apm\Impl\MetadataDiscoverer;
use Elastic\Apm\Impl\Tracer;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\MixedMap;
use ElasticApmTests\Util\Pair;
use ElasticApmTests\Util\TestCaseBase;
use ElasticApmTests\Util\TracerBuilderForTests;

class MetadataDiscovererUnitTest extends TestCaseBase
{
    public function testDefaultServiceNameUsesAgentName(): void
    {
        // https://github.com/elastic/apm/blob/main/specs/agents/configuration.md#zero-configuration-support
        // ... the default value: unknown-${service.agent.name}-service ...
        self::assertSame(
            'unknown-' . MetadataDiscoverer::AGENT_NAME . '-service',
            MetadataDiscoverer::DEFAULT_SERVICE_NAME
        );
    }

    private const FILE_NAME_TO_CONTENTS_KEY = 'file_name_to_contents';
    private const PROC_SELF_MOUNTINFO_FILE_NAME = '/proc/self/mountinfo';
    private const PROC_SELF_MOUNTINFO_CONTENTS_FROM_CONTAINER_KEY = 'proc_self_mountinfo_contents_from_container';
    private const PROC_SELF_MOUNTINFO_CONTENTS_FROM_CONTAINER = '
        857 856 0:61 / /proc rw,nosuid,nodev,noexec,relatime - proc proc rw
        863 861 0:33 /docker/c824705340063c4171d199fb6c95f94ff4966e29c77a7ad34d88b6f53a89f1df /sys/fs/cgroup/devices ro,nosuid,nodev,noexec,relatime master:15 - cgroup cgroup rw,devices
        871 861 0:41 /docker/c824705340063c4171d199fb6c95f94ff4966e29c77a7ad34d88b6f53a89f1df /sys/fs/cgroup/cpuset ro,nosuid,nodev,noexec,relatime master:23 - cgroup cgroup rw,cpuset
        875 858 0:66 / /dev/shm rw,nosuid,nodev,noexec,relatime - tmpfs shm rw,size=65536k
        876 856 8:5 /var/lib/docker/containers/c824705340063c4171d199fb6c95f94ff4966e29c77a7ad34d88b6f53a89f1df/resolv.conf /etc/resolv.conf rw,relatime - ext4 /dev/sda5 rw,errors=remount-ro
        877 856 8:5 /var/lib/docker/containers/c824705340063c4171d199fb6c95f94ff4966e29c77a7ad34d88b6f53a89f1df/hostname /etc/hostname rw,relatime - ext4 /dev/sda5 rw,errors=remount-ro
        878 856 8:5 /var/lib/docker/containers/c824705340063c4171d199fb6c95f94ff4966e29c77a7ad34d88b6f53a89f1df/hosts /etc/hosts rw,relatime - ext4 /dev/sda5 rw,errors=remount-ro
        674 857 0:61 /bus /proc/bus ro,nosuid,nodev,noexec,relatime - proc proc rw
        675 857 0:61 /fs /proc/fs ro,nosuid,nodev,noexec,relatime - proc proc rw
        676 857 0:61 /irq /proc/irq ro,nosuid,nodev,noexec,relatime - proc proc rw
        677 857 0:61 /sys /proc/sys ro,nosuid,nodev,noexec,relatime - proc proc rw
        ';
    private const PROC_SELF_MOUNTINFO_CONTENTS_FROM_NON_CONTAINER_KEY = 'proc_self_mountinfo_contents_from_non_container';
    private const PROC_SELF_MOUNTINFO_CONTENTS_FROM_NON_CONTAINER = '
        24 29 0:22 / /sys rw,nosuid,nodev,noexec,relatime shared:7 - sysfs sysfs rw
        25 29 0:23 / /proc rw,nosuid,nodev,noexec,relatime shared:14 - proc proc rw
        26 29 0:5 / /dev rw,nosuid,noexec,relatime shared:2 - devtmpfs udev rw,size=1970232k,nr_inodes=492558,mode=755
        28 29 0:25 / /run rw,nosuid,nodev,noexec,relatime shared:5 - tmpfs tmpfs rw,size=400072k,mode=755
        29 1 8:5 / / rw,relatime shared:1 - ext4 /dev/sda5 rw,errors=remount-ro
        193 29 7:21 / /snap/gnome-3-34-1804/93 ro,nodev,relatime shared:113 - squashfs /dev/loop21 ro
        427 49 0:51 / /proc/sys/fs/binfmt_misc rw,nosuid,nodev,noexec,relatime shared:115 - binfmt_misc binfmt_misc rw
        196 29 8:1 / /boot/efi rw,relatime shared:117 - vfat /dev/sda1 rw,fmask=0077,dmask=0077,codepage=437,iocharset=iso8859-1,shortname=mixed,errors=remount-ro
        ';
    private const PROC_SELF_CGROUP_FILE_NAME = '/proc/self/cgroup';
    private const PROC_SELF_CGROUP_CONTENTS_FROM_CONTAINER_KEY = 'proc_self_cgroup_contents_from_container';
    private const PROC_SELF_CGROUP_CONTENTS_FROM_CONTAINER = '
        10:cpuset:/docker/c824705340063c4171d199fb6c95f94ff4966e29c77a7ad34d88b6f53a89f1df
        9:memory:/docker/c824705340063c4171d199fb6c95f94ff4966e29c77a7ad34d88b6f53a89f1df
        8:pids:/docker/c824705340063c4171d199fb6c95f94ff4966e29c77a7ad34d88b6f53a89f1df
        6:cpu,cpuacct:/docker/c824705340063c4171d199fb6c95f94ff4966e29c77a7ad34d88b6f53a89f1df
        2:devices:/docker/c824705340063c4171d199fb6c95f94ff4966e29c77a7ad34d88b6f53a89f1df
        1:name=systemd:/docker/c824705340063c4171d199fb6c95f94ff4966e29c77a7ad34d88b6f53a89f1df
        0::/system.slice/containerd.service
        ';
    private const PROC_SELF_CGROUP_CONTENTS_FROM_NON_CONTAINER_KEY = 'proc_self_cgroup_contents_from_non_container';
    private const PROC_SELF_CGROUP_CONTENTS_FROM_NON_CONTAINER = '
        8:cpuset:/
        7:pids:/user.slice/user-1000.slice/session-28.scope
        6:memory:/user.slice/user-1000.slice/session-28.scope
        4:devices:/user.slice
        1:name=systemd:/user.slice/user-1000.slice/session-28.scope
        0::/user.slice/user-1000.slice/session-28.scope
        ';

    private const FILE_CONTENTS_KEY_TO_CONTENTS
        = [
            self::PROC_SELF_MOUNTINFO_CONTENTS_FROM_CONTAINER_KEY     => self::PROC_SELF_MOUNTINFO_CONTENTS_FROM_CONTAINER,
            self::PROC_SELF_MOUNTINFO_CONTENTS_FROM_NON_CONTAINER_KEY => self::PROC_SELF_MOUNTINFO_CONTENTS_FROM_NON_CONTAINER,
            self::PROC_SELF_CGROUP_CONTENTS_FROM_CONTAINER_KEY        => self::PROC_SELF_CGROUP_CONTENTS_FROM_CONTAINER,
            self::PROC_SELF_CGROUP_CONTENTS_FROM_NON_CONTAINER_KEY    => self::PROC_SELF_CGROUP_CONTENTS_FROM_NON_CONTAINER,
        ];

    private const EXPECTED_CONTAINER_ID_KEY = 'expected_container_id';
    private const EXPECTED_CONTAINER_ID = 'c824705340063c4171d199fb6c95f94ff4966e29c77a7ad34d88b6f53a89f1df';

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestDetectContainerId(): iterable
    {
        /**
         * @return iterable<array<string, mixed>>
         */
        $generateDataSets = function (): iterable {
            /** @var array<Pair<?string, bool>> $mountinfoVariants */
            $mountinfoVariants = [
                new Pair(self::PROC_SELF_MOUNTINFO_CONTENTS_FROM_CONTAINER_KEY, true),
                new Pair(self::PROC_SELF_MOUNTINFO_CONTENTS_FROM_NON_CONTAINER_KEY, false),
                new Pair(null, false),
            ];
            /** @var array<Pair<?string, bool>> $cgroupVariants */
            $cgroupVariants = [
                new Pair(self::PROC_SELF_CGROUP_CONTENTS_FROM_CONTAINER_KEY, true),
                new Pair(self::PROC_SELF_CGROUP_CONTENTS_FROM_NON_CONTAINER_KEY, false),
                new Pair(null, false),
            ];
            $fileNameToContents = [];
            foreach ($mountinfoVariants as $mountinfoVariant) {
                $fileNameToContents[self::PROC_SELF_MOUNTINFO_FILE_NAME] = $mountinfoVariant->first;
                foreach ($cgroupVariants as $cgroupVariant) {
                    $fileNameToContents[self::PROC_SELF_CGROUP_FILE_NAME] = $cgroupVariant->first;
                    yield [
                        self::FILE_NAME_TO_CONTENTS_KEY => $fileNameToContents,
                        self::EXPECTED_CONTAINER_ID_KEY => $mountinfoVariant->second || $cgroupVariant->second ? self::EXPECTED_CONTAINER_ID : null,
                    ];
                }
            }
        };

        return DataProviderForTestBuilder::convertEachDataSetToMixedMapAndAddDesc($generateDataSets);
    }

    /**
     * @dataProvider dataProviderForTestDetectContainerId
     */
    public function testDetectContainerId(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        /** @var array<string, ?string> $fileNameToContentsKey */
        $fileNameToContentsKey = $testArgs->getArray(self::FILE_NAME_TO_CONTENTS_KEY);
        $expectedContainerId = $testArgs->getNullableString(self::EXPECTED_CONTAINER_ID_KEY);
        $tracer = TracerBuilderForTests::startNew()->build();
        self::assertInstanceOf(Tracer::class, $tracer);
        $actualContainerId = (new MetadataDiscoverer($tracer->getConfig(), NoopLoggerFactory::singletonInstance()))->detectContainerIdImpl(
            function (string $fileName) use ($fileNameToContentsKey): ?string {
                self::assertArrayHasKey($fileName, $fileNameToContentsKey);
                $fileContentsKey = $fileNameToContentsKey[$fileName];
                if ($fileContentsKey === null) {
                    return null;
                }
                self::assertArrayHasKey($fileContentsKey, self::FILE_CONTENTS_KEY_TO_CONTENTS);
                return self::FILE_CONTENTS_KEY_TO_CONTENTS[$fileContentsKey];
            }
        );
        self::assertSame($expectedContainerId, $actualContainerId);
    }
}
