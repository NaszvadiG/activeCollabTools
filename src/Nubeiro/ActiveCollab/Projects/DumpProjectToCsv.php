<?php

namespace Nubeiro\ActiveCollab\Projects;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;

class ListTasksCommand extends Command
{

    protected $apiUrl = null;
    protected $apiToken = null;

    protected function configure()
    {
        $this->setName('project:dumpcsv')
            ->setDescription('Get a projects task list')
            ->addArgument('project_id', InputArgument::REQUIRED, 'Project id to get tasks from: i.e. "puntroma-magento"');
        $yaml = new Parser();
        $apiConfiguration = $yaml->parse(file_get_contents('config.yml'));

        $apiConfigKeys = array('apiUrl', 'apiToken');
        foreach ($apiConfigKeys as $field) {
            if (empty($apiConfiguration[$field])) {

                throw new \Exception("Missing config $field in config.yml");
            } else {

                $this->$field = $apiConfiguration[$field];
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $project_id = $input->getArgument('project_id');
        if ($project_id) {
            $categoryListById = $this->getProjectCategoryList($project_id, $output);
            $milestoneListById = $this->getProjectActiveMilestoneList($project_id, $output);
            $output->writeln("<info>Will get project task list for project with id $project_id </info>");
            $apiResponse = $this->apiRequestProjectTasks($project_id, $output);
            $csvLines = $this->parseProjectXml($apiResponse, $project_id, $output, $categoryListById, $milestoneListById);
            $fp = fopen($project_id . '.csv', 'w');
            foreach ($csvLines as $line) {
                fputcsv($fp, $line);
            }
        }
    }

    protected function getProjectActiveMilestoneList($project_id, $output)
    {
        $output->writeln('<info>Getting project active milestones ...</info>');
        $path_info = '/projects/' . $project_id . '/milestones';
        $apiResponse = $this->doApiRequest($path_info, $output);

        $milestonesAsXml = simplexml_load_string($apiResponse);

        $milestones = array();

        foreach ($milestonesAsXml as $milestoneNode) {

            $milestone_id = (string)$milestoneNode->{'id'};
            $milestone_name = (string)$milestoneNode->{'name'};
            $output->writeln("<comment>Got milestone $milestone_name with id $milestone_id</comment>");
            $milestones[$milestone_id] = $milestone_name;
        }

        return $milestones;
    }


    protected function getProjectCategoryList($project_id, $output)
    {
        $output->writeln('<info>Getting project categories ...</info>');
        $path_info = '/projects/' . $project_id . '/tasks/categories';
        $apiResponse = $this->doApiRequest($path_info, $output);

        $categoriesXml = simplexml_load_string($apiResponse);
        $categories = array();
        foreach ($categoriesXml as $categoryNode) {
            $category_id = (string)$categoryNode->{'id'};
            $category_name = (string) $categoryNode->{'name'};
            $output->writeln("<comment>Got category $category_name with id $category_id</comment>");
            $categories[$category_id] = $category_name;
        }

        return $categories;
    }

    protected function parseProjectXml($apiResponse, $project_id, $output, $categories, $milestones)
    {
        $output->writeln('<info>Parsing received xml.</info>');

        $xml = simplexml_load_string($apiResponse);

        $header= array('task_id', 'name', 'category_id', 'category', 'estimate', 'real_time', 'completed', 'milestone_id', 'milestone');
        $csvLines = array($header);

        foreach ($xml as $task) {
            $task_name = (string)$task->{'name'};
            $output->writeln('<info>Processing task '. $task_name .'</info>');

            $task_completed = (string)$task->{'is_completed'};
            $task_category_id = (string)$task->{'category_id'};
            $task_category = 'unknown';
            if (!empty($categories[$task_category_id])) {
                $task_category = $categories[$task_category_id];
            }
            $task_task_id = (string)$task->{'task_id'};
            $task_real_time = (string)$task->{'object_time'};
            $task_estimate = $this->apiRequestTaskEstimate($project_id, $task_task_id, $output);
            $task_milestone_id = (string)$task->{'milestone_id'};
            $task_milestone = 'unknown';
            if (!empty($milestones[$task_milestone_id])) {
                $task_milestone = $milestones[$task_milestone_id];
            }

            $line = array($task_task_id, $task_name, $task_category_id, $task_category, $task_estimate, $task_real_time, $task_completed, $task_milestone_id, $task_milestone);
            $csvLines[] = $line;
        }

        return $csvLines;
    }


    protected function apiRequestTaskEstimate($project_id, $task_id, $output)
    {
        $path_info = '/projects/' . $project_id . '/tasks/' . $task_id;
        $apiResponse = $this->doApiRequest($path_info, $output);
        $taskXml = simplexml_load_string($apiResponse);
        $estimate_value = (string)$taskXml->{'estimate'}->{'value'};

        return $estimate_value;
    }

    protected function doApiRequest($path_info, $output)
    {
        $apiUrl = $this->apiUrl . '?' . 'path_info='. $path_info . '&auth_api_token=' . $this->apiToken;
        $output->writeln('<comment>Using: ' . $apiUrl . '</comment>');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $apiResponse = curl_exec($ch);
        curl_close($ch);

        return $apiResponse;
    }

    protected function apiRequestProjectTasks($project_id, $output)
    {
        $path_info = '/projects/' . $project_id . '/tasks';
        $apiResponse = $this->doApiRequest($path_info, $output);

        return $apiResponse;
    }
}