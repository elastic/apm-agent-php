# Contributing to the Elastic APM PHP agent

The PHP APM Agent is open source and we love to receive contributions from our community — you!

There are many ways to contribute,
from writing tutorials or blog posts,
improving the documentation,
submitting bug reports and feature requests or writing code.

You can get in touch with us through [Discuss](https://discuss.elastic.co/c/apm),
feedback and ideas are always welcome.

## Code contributions (please read this before your first PR)

If you have a bugfix or new feature that you would like to contribute,
please find or open an issue about it first.
Talk about what you would like to do.
It may be that somebody is already working on it,
or that there are particular issues that you should know about before implementing the change.

We aim to maintain high code quality, therefore every PR goes through the same review process, no matter who opened the PR.

### General advice

Please make sure that your PR addresses a single issue and its size is reasonable to review. There are no hard rules, but please keep in mind that every line of your PR will be reviewed and it's not uncommon that a longer discussion evolves in case of a sensitive change.

Therefore it's preferred to have multiple smaller PRs over a single big PR.

This makes sure that changes that have consensus get merged quickly and don't get blocked by unrelated changes. Additionally, the repository will also have a useful and searchable history by doing so.

Please do:
- Try to get feedback as early as possible and prefer to ask questions in issues before you start submitting code.
- Add a description to your PR which describes your intention and gives a high level summary about your changes.
- Run all the tests from the `test` folder and make sure that all of them are green. See [Testing](###Testing).
- In case of new code, make sure it's covered by at least 1 test.
- make sure your IDE uses the `.editorconfig` from the repo and you follow our coding guidelines. See [Coding-guidelines](###Coding-guidelines).
- Feel free to close a PR and create a new one in case you changed your mind, or found a better solution.
- Feel free to fix typos.
- Feel free to use the draft PR feature of GitHub, in case you would like to show some work in progress code and get feedback on it.

Please don't:
- Create a PR where you address multiple issues at once.
- Create a giant PR with a huge change set. There is no hard rule, but if your change grows over 1000 lines, it's maybe worth thinking about making it self contained and submit it as a PR and address follow-up issues in a subsequent PR. (of course there can be exceptions)
- Actively push code to a PR until you have received feedback. Of course if you spot some minor things after you opened a PR, it's perfectly fine to push a small fix. But please don't do active work on a PR that haven't received any feedback. It's very possible that someone already looked at it and is about to write a detailed review. If you actively add new changes to a PR then the reviewer will have a hard time to provide up to date feedback. If you just want to show work-in-progress code, feel free to use the draft feature of github, or indicate in the title, that the work is not 100% done yet. Of course, once you have feedback on a PR, it's perfectly fine, or rather encouraged to start working collaboratively on the PR and push new changes and address issues and suggestions from the reviewer.
- Change or add dependencies, unless you are really sure about it (it's best to ask about this in an issue first) - see [compatibility](####compatibility).

### Submitting your changes

Generally, we require that you test any code you are adding or modifying.
Once your changes are ready to submit for review:

1. Sign the Contributor License Agreement

    Please make sure you have signed our [Contributor License Agreement](https://www.elastic.co/contributor-agreement/).
    We are not asking you to assign copyright to us,
    but to give us the right to distribute your code without restriction.
    We ask this of all contributors in order to assure our users of the origin and continuing existence of the code.
    You only need to sign the CLA once.

2. Build and package your changes

    Run the build and package goals to make sure that nothing is broken.
    See [development](#development) for details.

3. Test your changes

    Run the test suite to make sure that nothing is broken.
    See [testing](#testing) for details.

4. Rebase your changes

    Update your local repository with the most recent code from the main repo,
    and rebase your branch on top of the latest main branch.
    We prefer your initial changes to be squashed into a single commit.
    Later,
    if we ask you to make changes,
    add them as separate commits.
    This makes them easier to review.
    As a final step before merging, we will either ask you to squash all commits yourself or we'll do it for you.

5. Submit a pull request

    Push your local changes to your forked copy of the repository and [submit a pull request](https://help.github.com/articles/using-pull-requests).
    In the pull request,
    choose a title which sums up the changes that you have made,
    and in the body provide more details about what your changes do.
    Also mention the number of the issue where the discussion has taken place,
    eg "Closes #123".

6. Be patient

    We might not be able to review your code as fast as we would like to,
    but we'll do our best to dedicate it the attention it deserves.
    Your effort is much appreciated!

### Development

See [development documentation](DEVELOPMENT.md).

### Testing

Coming soon [TBD]

### Workflow

All feature development and most bug fixes hit the main branch first.
Pull requests should be reviewed by someone with commit access.
Once approved, the author of the pull request,
or reviewer if the author does not have commit access,
should "Squash and merge".

### Design considerations

#### Performance

The agent is designed to monitor production applications. Therefore it's very important to keep the performance overhead of the agent as low as possible.

It's not uncommon that you write or change code that can potentially change the performance characteristics of the agent and therefore also of the application's of our users.

##### Performance testing
    
Coming soon [TBD]

#### Compatibility
    
Coming soon [TBD]

### Coding guidelines

Coming soon [TBD]

### Adding support for instrumenting new libraries/frameworks/APIs

Coming soon

### Releasing

Coming soon.
