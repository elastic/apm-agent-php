module.exports = {
    "username": "elastic",
    "repo": "apm-agent-php",
    "dataSource": "prs",
    "groupBy": {
        "Breaking changes": ["breaking", "automation"],
        "Bug Fixes": ["bug", "fix"],
        "Features": ["enhancement", "internal", "feature", "feat", "documentation"]
    },
    "template": {
        issue: function (placeholders) {
          return '* ' + placeholders.name + '{pull}' + placeholders.text.replace("#", "") + '[' + placeholders.text + ']';
        },
        changelogTitle: "",
        release: "[[release-notes-{{release}}]]\n==== {{release}} - {{date}}\n{{body}}",
        releaseSeparator: "",
        group: function (placeholders) {
          return '\n[float]\n===== ' + placeholders.heading;
        }
    }
}
