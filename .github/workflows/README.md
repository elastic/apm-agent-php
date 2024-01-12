## CI/CD

There are four main stages that run on GitHub actions:

* Linting
* Build
* Test
* Package

Some other stages run for every push on the main branches:
* [Snapshoty](./snapshoty.yml)

### Scenarios

* Tests should be triggered on branch, tag, and PR basis.
* Commits only affecting the docs files should not trigger any test or similar stages that are not required.
* Automated release in the CI gets triggered when a tagged release is created.
* **This is not the case yet**, but if Github secrets are required then Pull Requests from forked repositories won't run any build accessing those secrets. If needed, then create a feature branch.

### How to interact with the CI?

#### On a PR basis

Once a PR is opened then, there are two different ways you can trigger builds in the CI:

1. Commit based
2. UI-based, any Elasticians can force a build through the GitHub UI

#### Branches

Whenever there is a merge to `main`` or any release branches, the whole workflow will compile and test every entry in the compatibility matrix for Linux and Windows.

### Release process

This process has been fully automated and gets triggered when a tagged release is created.
The tag release follows the naming convention: `v.<major>.<minor>.<patch>`, where `<major>`, `<minor>` and `<patch>`.

The [release](https://github.com/elastic/apm-agent-php/actions/workflows/release.yml) workflow is the one driving the whole automation.

#### Implementation details

The release workflow uses the Buildkite pipeline called `observability-robots-php-release`, which relies on the Buildkite API token provided by the Service account called `obltmachine`.

The `observability-robots-php-release` uses a Buildkite API token to call the
relevant GPG signing Buildkite pipeline and retrieve the artifacts.
Unfortunately, Buildkite does not fully support secretless access to retrieve data between parentstream/downstream pipelines.

As long as `upload-artifact@v3` is used, see https://github.com/cli/cli/issues/5625, it's not
possible to use the `ghcli` to fetch the artifacts; that's the reason for using a Google bucket for uploading the unsigned artifacts. Afterward, the Buildkite pipeline will fetch
those artifacts to sign them accordingly.


### OpenTelemetry

A GitHub workflow is responsible for populating what the workflow runs regarding jobs and steps. Those details can be seen [here](https://ela.st/oblt-ci-cd-stats) (**NOTE**: only available for Elasticians).

### Bump automation

[updatecli](https://www.updatecli.io/) is the tool to update the [APM agent](./updatecli.yml)'s specs automatically.
