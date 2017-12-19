#!/usr/bin/env php
<?php
/**
 * <bitbar.title>Github CI Status</bitbar.title>
 * <bitbar.version>v1.0</bitbar.version>
 * <bitbar.author>Jordan Andree</bitbar.author>
 * <bitbar.author.github>jordanandree</bitbar.author.github>
 * <bitbar.desc>Displays Github Pull Request CI Check statuses</bitbar.desc>
 * <bitbar.dependencies>php</bitbar.dependencies>
 * <bitbar.abouturl>https://github.com/jordanandree/bitbar-github-ci</bitbar.abouturl>
 */

class GithubCIStatus
{
    /**
     * Default config values
     *
     * @var array
     */
    protected $default_config = [
        "hostname" => "github.com",
        "title"    => "",
    ];

    /**
     * Config options sourced from ~/.bitbarrc
     *
     * @var stdClass
     */
    protected $config;

    /**
     * Status line template for each check
     *
     * @var string
     */
    protected $status_line = "%s %s|href=%s";

    /**
     * State of CI checks
     *
     * @var string
     */
    protected $state = "success";

    /**
     * Lock param for mutating state
     *
     * @var bool
     */
    protected $state_lock = false;

    /**
     * Output the Pull Request checks
     *
     * @return string
     */
    public function run()
    {
        $lines = [];
        try {
            $pull_requests = $this->searchPullRequests();

            if (empty($pull_requests)) {
                $this->sendOutput("No Pull Requests. Get to work!");
                exit;
            }

            foreach ($pull_requests as $pr) {
                $repo_name = substr($pr->repository_url, strlen($this->getConfig()->base_uri . "repos/"));

                $pr_info = $this->getPullRequest($repo_name, $pr->number);
                $status  = $this->getCommitStatus($repo_name, $pr_info->head->sha);
                $icon    = $this->statusIcon($status->state);
                $lines[] = sprintf($this->status_line, $icon, $pr_info->title, $pr_info->html_url);

                if ($status->state !== $this->state && !$this->state_lock) {
                    $this->state = $status->state;
                    $this->state_lock = true;
                }

                foreach ($status->statuses as $check) {
                    $check_icon = $this->statusIcon($check->state);
                    $lines [] = "--" . sprintf($this->status_line, $check_icon, $check->context, $check->target_url);
                }
            }
        } catch (RuntimeException $e) {
            $this->state = "warning";
            $this->sendOutput($e->getMessage());
            exit;
        }

        $this->sendOutput($lines);
    }

    /**
     * echo back output to bitbar
     *
     * @param string[]|string
     *
     * @return void
     */
    public function sendOutput($lines)
    {
        echo $this->getConfig()->title;
        echo " " . $this->statusIcon($this->state);
        echo "\n---\n";

        if (is_array($lines)) {
            echo implode($lines, "\n");
        } else {
            echo $lines . "\n";
        }
    }

    /**
     * Get the memoized configuration struct or set it
     *
     * @return stdClass
     *
     * @throws RuntimeException
     */
    protected function getConfig()
    {
        if (is_null($this->config)) {
            $bitbarrc = getenv('HOME') . "/.bitbarrc";

            if (!file_exists($bitbarrc)) {
                throw new RuntimeException("~/.bitbarrc is missing");
            }

            $config = parse_ini_file($bitbarrc, true);

            if (!array_key_exists("github_ci", $config)) {
                throw new RuntimeException("[github_ci] section is missing in ~/.bitbarrc");
            }

            $config = array_merge($this->default_config, $config["github_ci"]);
            $config["base_uri"] = "https://" . $config["hostname"] . "/api/v3/";
            $this->config = (object) $config;
        }

        return $this->config;
    }

    /**
     * Send a Request to the Github API
     *
     * @param string $endpoint
     * @param array $options
     *
     * @return stdClass
     *
     * @throws RuntimeException
     */
    protected function sendRequest($endpoint, $params = [])
    {
        $url = $this->getConfig()->base_uri . $endpoint;
        $params["access_token"] = $this->getConfig()->access_token;
        $url .= "?" . http_build_query($params);

        $headers = null;
        $body = null;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $response = curl_exec($ch);

        if (!empty($response)) {
            list($headers, $body) = explode("\r\n\r\n", $response, 2);
        }

        curl_close($ch);

        if (is_null($body)) {
            throw new RuntimeException("Error making request to the Github API. Check your configuration.");
        } else {
            return json_decode($body);
        }
    }

    /**
     * Get a single Pull Request
     *
     * @param string $repo
     * @param int $id
     *
     * @return stdClass
     */
    protected function getPullRequest($repo, $id)
    {
        return $this->sendRequest("repos/$repo/pulls/$id");
    }

    /**
     * Get the status of a single Commit
     *
     * @param string $repo
     * @param string $sha
     *
     * @return stdClass
     */
    protected function getCommitStatus($repo, $sha)
    {
        return $this->sendRequest("repos/$repo/commits/$sha/status");
    }

    /**
     * Perform search for open issues by the author in the repos
     *
     * @return stdClass
     */
    protected function searchPullRequests()
    {
        $q = "state:open author:" . $this->getConfig()->username;
        foreach ($this->getConfig()->repos as $repo) {
          $q .= " repo:$repo";
        }

        return $this->sendRequest("search/issues", [
            "q" => $q,
        ])->items;
    }

    /**
     * Get the icon for the status
     *
     * @param string $status
     *
     * @return string
     */
    protected function statusIcon($status)
    {
        $map = [
            "failure" => "🆘",
            "pending" => "🔄",
            "success" => "✅",
            "warning" => "⚠️",
        ];

        return $map[$status];
    }
}

echo (new GithubCIStatus())->run();
