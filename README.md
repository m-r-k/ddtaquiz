

# DDTA Quiz for Moodle
The "DDTA Quiz" (Digital Diagnostive Test Assignment) activity is a plugin for the open-source learning platform Moodle.
Like the standard "Quiz" activity, a teacher can create a test for students to take.
The test consist of a number of questions aswell, but in contrast to the standard quiz 
it is possible to show questions depending on the performance of the student in previous questions.

# Purpose
The conditional display of certain questions allows a multitude of scenarios.
The main motivation for the development of this plugin is to have a possibility 
to detect the cause of student errors on a fine grained level.

For example a student might be asked to find the local extremum of a parabola.
If the question is answered correctly, the student shall just proceed with the test.
If it is answered incorrectly however, there will be extra questions to detect the cause of 
the incorrect answer. In this scenarion this will most likely be a task to find the derivative of 
the parabola's function and to find the root of it.

# Feedback
At the end of the quiz the student will see a feedback similar to the one of the standard quiz, for 
the questions that were displayed. The feedback can be replaced by an adaptive feedback for subsets 
of the questions though. This allows a teacher to give more detailed feedback, not only for one question, 
but for a combination of multiple questions.

Continuing the example of the parabola, a student might not have found the extremum, but was able to 
find the derivative and the roots of the derivative. In this case the feedback could include an 
explanation of how to determine the extremum from the roots of the derivative.

# Init

Inside amd folder run this command :

    $ grunt amd --force