## Github CI Status for BitBar

Displays an icon with a dropdown containing status of continuous integration checks across Pull Requests you've authored.

![screenshot](https://raw.githubusercontent.com/jordanandree/bitbar-github-ci/29fb903/screenshot.jpg )

### Setup

First, generate a [Personal Access Token](https://github.com/settings/tokens) with permissions for:

- **repo**:  Full control of private repositories

While this plugin does nothing more than read the status of Pull Requests, the Github Access Token permissions requires that full repo access be granted for commit status checks to be read.

Next, clone the plugin and copy the script to your plugins directory:

```
git clone git@github.com:jordanandree/bitbar-github-ci.git
cp ./bitbar-github-ci/github-ci.1m.php ./path/to/plugins/
```

Next, edit or create the configuration file `~/.bitbarrc` in your home directory:

```
# ~/.bitbarrc
[github_ci]
access_token=xxx
username=jordanandree
repos[]=jordanandree/bitbar-github-ci
repos[]=jordanandree/dotfiles

# If using Github Enterprise, change this to your GHE hostname
# hostname=mygit.example.com
```

Finally, the plugin should then start pulling in status checks for each Pull Request found for the configured repositories.
Each Pull Request will have a submenu for the list of checks associated with it.
