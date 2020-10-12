module.exports = {
    "username": "elastic",
    "repo": "apm-agent-php",
    "dataSource": "prs",
    "groupBy": {
        "Breaking changes": ["breaking"],
        "Bug fixes": ["bug", "fix"],
        "Features": ["enhancement", "internal", "feature", "feat"]
    },
    "template": {
        issue: function (placeholders) {
          return '* ' + placeholders.name + ': {pull}' + placeholders.text.replace("#", "") + '[' + placeholders.text + ']';
        },
        changelogTitle: "",
        release: "[[release-notes-{{release}}]]\n==== {{release}}\n{{body}}",
        releaseSeparator: "",
        group: function (placeholders) {
          return '\n[float]\n===== ' + placeholders.heading;
        }
    }
}
