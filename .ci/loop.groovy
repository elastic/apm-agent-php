#!/usr/bin/env groovy

@Library('apm@current') _

pipeline {
  agent { label 'linux && docker && ubuntu-18.04 && immutable' }
  environment {
    REPO = 'apm-agent-php'
    BASE_DIR = "src/go.elastic.co/apm/${env.REPO}"
    SLACK_CHANNEL = '#apm-agent-php'
    NOTIFY_TO = 'build-apm+apm-agent-php@elastic.co'
    ONLY_DOCS = "false"
    GITHUB_CHECK_ITS_NAME = 'Integration Tests'
    ITS_PIPELINE = 'apm-integration-tests-selector-mbp/master'
  }
  options {
    buildDiscarder(logRotator(numToKeepStr: '20', artifactNumToKeepStr: '20', daysToKeepStr: '30'))
    timestamps()
    ansiColor('xterm')
    disableResume()
    durabilityHint('PERFORMANCE_OPTIMIZED')
    rateLimitBuilds(throttle: [count: 60, durationName: 'hour', userBoost: true])
    quietPeriod(10)
    timeout(time: 3, unit: 'HOURS')
  }
  triggers {
    issueCommentTrigger('(?i)/loop\\W+test')
  }
  stages {
    stage('Checkout') {
      steps {
        whenTrue(isInternalCI() && isTag()) {
          notifyStatus(slackStatus: 'good', subject: "[${env.REPO}] Release tag *${env.TAG_NAME}* has been created", body: "Build: (<${env.RUN_DISPLAY_URL}|here>) for further details.")
        }
        pipelineManager([ cancelPreviousRunningBuilds: [ when: 'PR' ] ])
        deleteDir()
        gitCheckout(basedir: "${BASE_DIR}", githubNotifyFirstTimeContributor: true)
        stash allowEmpty: true, name: 'source', useDefaultExcludes: false
      }
    }
    stage('BuildAndTest') {
      failFast false
      matrix {
        agent { label 'linux && docker && ubuntu-18.04 && immutable' }
        options { skipDefaultCheckout() }
        axes {
          axis {
            name 'PHP_VERSION'
            values '7.2', '7.3', '7.4'
          }
          axis {
            name 'DOCKERFILE'
            values 'Dockerfile', 'Dockerfile.alpine'
          }
        }
        stages {
          stage('Build') {
            steps {
              deleteDir()
              unstash 'source'
              dir("${BASE_DIR}"){
                // When running in the CI with multiple parallel stages
                // the access could be considered as a DDOS attack.
                retryWithSleep(retries: 3, seconds: 5, backoff: true) {
                  sh script: "PHP_VERSION=${PHP_VERSION} DOCKERFILE=${DOCKERFILE} make -f .ci/Makefile prepare", label: 'prepare docker image'
                }
                sh script: "PHP_VERSION=${PHP_VERSION} DOCKERFILE=${DOCKERFILE} make -f .ci/Makefile build", label: 'build'
              }
            }
          }
          stage('Tests loop') {
            steps {
              dir("${BASE_DIR}"){
                sh script: "PHP_VERSION=${PHP_VERSION} DOCKERFILE=${DOCKERFILE} make -f .ci/Makefile loop", label: 'test'
              }
            }
            post {
              always {
                junit(allowEmptyResults: true, keepLongStdio: true, testResults: "${BASE_DIR}/**/log_as_junit.xml,${BASE_DIR}/junit.xml")
              }
            }
          }
        }
      }
    }
  }
  post {
    cleanup {
      ## PR comment is not needed with this pipeline
      notifyBuildResult(prComment: false)
    }
  }
}
