#!/usr/bin/env groovy

@Library('apm@current') _

pipeline {
  agent { label 'linux && docker && ubuntu-18.04 && immutable' }
  environment {
    REPO = 'apm-agent-php'
    BASE_DIR = "src/go.elastic.co/apm/${env.REPO}"
    NOTIFY_TO = 'build-apm+apm-agent-php@elastic.co'
    LOOPS = "${params.LOOPS}"
  }
  options {
    buildDiscarder(logRotator(numToKeepStr: '20', artifactNumToKeepStr: '20', daysToKeepStr: '30'))
    timestamps()
    ansiColor('xterm')
    disableResume()
    durabilityHint('PERFORMANCE_OPTIMIZED')
    rateLimitBuilds(throttle: [count: 60, durationName: 'hour', userBoost: true])
    quietPeriod(10)
    timeout(time: 5, unit: 'HOURS')
  }
  triggers {
    issueCommentTrigger('(?i).*jenkins\\W+run\\W+(?:the\\W+)?loop\\W+tests(?:\\W+please)?.*')
  }
  parameters {
    string(name: 'LOOPS', defaultValue: '200', description: 'How many test loops?')
  }
  stages {
    stage('Checkout') {
      steps {
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
                sh script: "LOOPS=${LOOPS} PHP_VERSION=${PHP_VERSION} DOCKERFILE=${DOCKERFILE} make -f .ci/Makefile loop", label: 'test'
              }
            }
            post {
              always {
                junit(allowEmptyResults: true, keepLongStdio: true, testResults: "${BASE_DIR}/**/log_as_junit*.xml,${BASE_DIR}/junit*.xml")
                archiveArtifacts(allowEmptyArchive: true, artifacts: 'build/*.txt')
              }
            }
          }
        }
      }
    }
  }
  post {
    cleanup {
      // PR comment is not needed with this pipeline
      notifyBuildResult(prComment: false)
    }
  }
}
