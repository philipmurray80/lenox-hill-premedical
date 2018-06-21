var isNormalizeOk = false;

function processSelection(e) {
    // Get the selection
    var userSelection = window.getSelection();

    //Make sure it's a legitimate selection
    var range = (filterSelection(userSelection));
    if (!range) {return false;}

//Prepare the highlight button
    var button = document.getElementById('highlightButton');
    //Set the coordinates
    button.style.top = e.pageY+'px';
    button.style.left = e.pageX+'px';

    //Attach the appropriate onmousedown listener
    button.onmousedown = function () {
        highlightRange(range);
        document.getElementById('highlightButton').style.display = 'none';
        window.getSelection().removeAllRanges();
        window.event.stopPropagation();
    }

    //display it!
    button.style.display = "inline";
	userSelection.removeAllRanges();
	userSelection.addRange(range);

    //focus on it.
    button.focus();
}

function getTextNodesIn(node) {
    var textNodes = [];
    if (node) {
        if (node.nodeType == 3) {
            textNodes.push(node);
        } else {
            var children = node.childNodes;
            for (var i = 0, len = children.length; i < len; ++i) {
                textNodes.push.apply(textNodes, getTextNodesIn(children[i]));
            }
        }
    }
    return textNodes;
}

function hideHighlightButton() {
    document.getElementById('highlightButton').style.display = 'none';
}

function highlightRange(range) {
    var aC = document.getElementById('annotationCount').value;
    aC++;
    var rangeArray = getArray(range);

    //Highlight the safe ranges
    for (var j = 0; j<rangeArray.length; j++) {
        //If neither end is a text node:
        // 1. Get all the siblings between end nodes (inclusive),
        // 2. Get the text nodes contained in each of them.
        // 3. Check to see if they're already highlighted.
        // 4. If not, highlight them.
        if (rangeArray[j].startContainer.nodeType != 3 && rangeArray[j].endContainer.nodeType != 3) {//Neither ends are text.
            for (var i = rangeArray[j].startOffset; i < rangeArray[j].endOffset; i++) {
                var textNodes = getTextNodesIn(rangeArray[j].startContainer.childNodes[i]);
                for (var k = 0; k < textNodes.length; k++) {
                    if (textNodes[k].parentNode.nodeName != 'B') { //text node not highlighted
                        var text = textNodes[k].cloneNode().textContent;
                        var b = document.createElement('b');
                        b.setAttribute('class', aC);
                        b.textContent = text;
                        b.onmousedown = function() {removeHighlightGroup(this)};
                        textNodes[k].parentNode.insertBefore(b, textNodes[k]);
                        textNodes[k].parentNode.removeChild(textNodes[k]);
                    }
                }
            }
        } else {
            var b = document.createElement('b');
            b.setAttribute('class', aC);
            b.onmousedown = function() {removeHighlightGroup(this)};
            rangeArray[j].surroundContents(b);
        }
    }

    document.getElementById('annotationCount').value = aC;
    document.getElementById('annotationChanged').value = 'TRUE';
}

function removeHighlightGroup(bTag) {
    var aG = document.getElementsByClassName(bTag.className);
    while (aG.length) {
        removeBTag(aG[aG.length - 1]);
    }
    var passage = document.getElementById('passage');
    mcatNormalize(passage);
	document.getElementById('annotationChanged').value = 'TRUE';
    window.event.stopPropagation();
}

function removeBTag(bTag) {
    mcatNormalize(bTag);
    if (bTag.hasChildNodes()) {
        if (bTag.childNodes.length == 1 && bTag.firstChild.nodeType == 3) {
            bTag.parentNode.insertBefore(bTag.firstChild, bTag);
            bTag.parentNode.removeChild(bTag);
        } else {
            var children = bTag.childNodes;
            for (var i = 0; i < children.length; i++) {
                if (children[i].nodeType != 3) {
                    removeBTag(children[i]);
                }
            }
        }
    } else {//Empty tag. Sometime this happens if you go bananas with highlighting.
        bTag.parentNode.removeChild(bTag);
    }
}

function getArray(range) {
    var anc = range.commonAncestorContainer;

    var beginningNodes = [];
    var beginningRanges = [];
    if (range.startContainer != anc) {
        for (var i = range.startContainer; i != anc; i = i.parentNode) {
            beginningNodes.push(i);
        }
        if (0 < beginningNodes.length) {
            for (var i = 0; i < beginningNodes.length; i++) {
                var newRange = document.createRange();
                if (i) {
                    newRange.setStartAfter(beginningNodes[i - 1]);
                    newRange.setEndAfter(beginningNodes[i].lastChild);
                } else {
                    newRange.setStart(beginningNodes[i], range.startOffset);
                    newRange.setEndAfter((beginningNodes[i].nodeType == Node.TEXT_NODE) ? beginningNodes[i] : beginningNodes[i].lastChild);
                }
                if (!newRange.collapsed) {
                    beginningRanges.push(newRange);
                }
            }
        }
    }

    var finalNodes = [];
    var finalRanges = [];
    if (range.endContainer != anc) {
        for (var i = range.endContainer; i != anc; i = i.parentNode) {
            finalNodes.push(i);
        }
        if (0 < finalNodes.length) {
            for (var i = 0; i < finalNodes.length; i++) {
                var newRange = document.createRange();
                if (i) {
                    newRange.setStartBefore(finalNodes[i].firstChild);
                    newRange.setEndBefore(finalNodes[i - 1]);
                } else {
                    newRange.setStartBefore((finalNodes[i].nodeType == Node.TEXT_NODE) ? finalNodes[i] : finalNodes[i].firstChild);
                    newRange.setEnd(finalNodes[i], range.endOffset);
                }
                if (!newRange.collapsed) {
                    finalRanges.unshift(newRange);
                }
            }
        }
    }

    if ((0 < beginningNodes.length) && (0 < finalNodes.length)) {
        var middleRange = document.createRange();
        middleRange.setStartAfter(beginningNodes[beginningNodes.length - 1]);
        middleRange.setEndBefore(finalNodes[finalNodes.length - 1]);
        if (!middleRange.collapsed) {
            beginningRanges.push(middleRange);
        }
    }

    var rangeArray = beginningRanges.concat(finalRanges);
    if (rangeArray.length == 0) {
        return [range];
    } else {
        return rangeArray;
    }
}

function filterSelection(selection) {
    //This function takes the raw selection and makes sure it's a legitimate range for highlighting.
    //If it's not, returns false

    if (selection.rangeCount != 1) {
        return false;
    }
    var range = selection.getRangeAt(0);

    if (range.collapsed == true) {
        return false;
    }

    //Make sure they are in the passage box
    var cA = range.commonAncestorContainer;
    var p = document.getElementById('passage');
    while (cA != p) {
        if (cA.nodeName == 'BODY') {
            return false;
        } else {
            cA = cA.parentNode;
        }
    }

    return range;
}

function initializeAnnotations() {
    var bTags = document.getElementById('passage').getElementsByTagName('b');
    for (var i = 0; i < bTags.length; i++) {
        bTags[i].onmousedown = function() {removeHighlightGroup(this)};
    }
}

function checkIfNormalizeOk() {
    var p = document.createElement('p');

    // you can not put empty strings -- put blank strings instead
    p.appendChild( document.createTextNode(' ') );
    p.appendChild( document.createTextNode(' ') );
    p.appendChild( document.createTextNode(' ') );

    document.getElementsByTagName('head')[0].appendChild(p);
    p.normalize();
    if (p.childNodes.length === 1) {
        isNormalizeOk = true;
    }
    document.getElementsByTagName('head')[0].removeChild(p);
}

function getNextNode(node, ancestor, isOpenTag) {
    if (typeof isOpenTag === 'undefined') {
        isOpenTag = true;
    }
    var next;
    if (isOpenTag) {
        next = node.firstChild;
    }
    next = next || node.nextSibling;
    if (!next && node.parentNode && node.parentNode !== ancestor) {
        return getNextNode(node.parentNode, ancestor, false);
    }
    return next;
}

function mcatNormalize(el) {
    if (isNormalizeOk) {
        el.normalize();
    } else {
        var adjTextNodes = [], nodes, node = el;
        while ((node = getNextNode(node, el))) {
            if (node.nodeType === 3 && node.previousSibling && node.previousSibling.nodeType === 3) {
                if (!nodes) {
                    nodes = [node.previousSibling];
                }
                nodes.push(node);
            } else if (nodes) {
                adjTextNodes.push(nodes);
                nodes = null;
            }
        }

        adjTextNodes.forEach(function (nodes) {
            var first;
            nodes.forEach(function (node, i) {
                if (i > 0) {
                    first.nodeValue += node.nodeValue;
                    node.parentNode.removeChild(node);
                } else {
                    first = node;
                }
            });
        });
    }
}

//-----------------------------------------------------------------------------------------------

function getPeriodicTable(url) {
	newWindow = window.open(url, 'Periodic Table', 'height=700,width=1100');
	if (window.focus) {newWindow.focus();}
	return false;
}

function focusForMarking(question) {
	var fieldsetArray = document.getElementsByTagName(question.tagName);
	for (var i = 0; i < fieldsetArray.length; i++) {
		fieldsetArray[i].style.display = 'none';
	}
	question.style.display = 'block';
	
	var questionNumber = question.getElementsByTagName('input')[0].name;
    var previousButton = document.getElementById('previousButton');
    if (previousButton) {//don't need to black out previous button if student is reviewing.
        previousButton.style.visibility = 'visible';
        if (questionNumber == 1 || questionNumber == 60 || questionNumber == 113 || questionNumber == 172) {
            previousButton.style.visibility = 'hidden';
        }
    }
	
	modifyMarkButton(question);
}

function modifyMarkButton(question) {
	var itemNumber = question.getElementsByTagName('input')[1].name;
	var markButton = document.getElementById('mark_button');
	markButton.setAttribute('currentItem', itemNumber);
	if (isMarked(question)) {
		markButton.setAttribute("class", "mcat_marked_button");
		markButton.value = "MARKED";
	} else {
		markButton.setAttribute("class", "mcat_submit_button");
		markButton.value = 'MARK';
	}
}

function isMarked(question) {
	var inputTag = question.getElementsByTagName('input')[1];
	if (inputTag.value.substr(1,1) == "m") {
		return true;
	} else {
		return false;
	}
}

function clickMarkButton() {
	var markButton = document.getElementById('mark_button');
	var currentItem = markButton.getAttribute('currentItem');
	var fieldsetArray = document.getElementsByTagName('fieldset');
	
	for (var i = 0; i < fieldsetArray.length; i++) {
		if (fieldsetArray[i].getElementsByTagName('input')[0].name == currentItem) {
			var question = fieldsetArray[i];
			break;
		}
	}
	removeExtraField(question);
	if (isMarked(question) == false) {
		markItem(question);
	} else {
		unmarkItem(question);
	}
	updateExtraField(question);
	modifyMarkButton(question);
}

function markItem(question) {
	var inputArray = question.getElementsByTagName('input');
	for (var i = 0; i < inputArray.length; i++) {
			inputArray[i].value = inputArray[i].value.concat("m");
	}
}

function unmarkItem(question) {
	var inputArray = question.getElementsByTagName('input');
	for (var i = 0; i < inputArray.length; i++) {
			inputArray[i].value = inputArray[i].value.substr(0,1);
	}
}

function modifyMarkButton(question) {
	var firstItem = question.getElementsByTagName('input')[0];
	itemNumber = firstItem.name;
	var markButton = document.getElementById('mark_button');
	markButton.setAttribute('currentItem', itemNumber);
	if (firstItem.value.substr(1,1) == "m") {
		markButton.setAttribute("class", "mcat_marked_button");
		markButton.value = "MARKED";
	} else {
		markButton.setAttribute("class", "mcat_submit_button");
		markButton.value = 'MARK';
	}
}

function initializeMcatChecks() {
	 var fieldsetArray = document.getElementsByTagName('fieldset');
	 for (var i=0; i<fieldsetArray.length; i++) {
		var inputTags = fieldsetArray[i].getElementsByTagName('input');
	 	for (var j=0; j<inputTags.length; j++) {
	 		if (inputTags[j].getAttribute('checked') == "checked") {
	 			inputTags[j].setAttribute('mcat_checked', "checked");
	 		} else {
	 			inputTags[j].setAttribute('mcat_checked', "unchecked");
	 		}
	 	}
	 }
}

function initializeExtraFields() {
	var fieldsetArray = document.getElementsByTagName('fieldset');
	for (var i=0; i<fieldsetArray.length; i++) {
		updateExtraField(fieldsetArray[i]);
	}
}

function mcatCheck(obj) {
	 var question = obj.parentNode.parentNode.parentNode;
	 removeExtraField(question);
	 if (obj.getAttribute('mcat_checked') == 'checked') {
		 	obj.checked = false;
		 	obj.setAttribute('mcat_checked', "unchecked");
	 } else {	
		 var inputArray = question.getElementsByTagName('input');
		 for (var i=0; i<inputArray.length; i++) {
			 inputArray[i].setAttribute('mcat_checked', 'unchecked');
		 }
		 obj.setAttribute('mcat_checked', "checked");
		 var answerText = obj.parentNode.nextSibling.firstChild;
		 if (isStrikedOut(answerText)) {
			 unStrikeOut(answerText);
		 }
	 }
	 updateExtraField(question);
}

function removeExtraField(question) {
	var itemNumber = question.getElementsByTagName('input')[1].name;
	var idString = 'extra'.concat(itemNumber);
	var element = document.getElementById(idString);
	if (element !== null) {
		element.parentNode.removeChild(element);
	}
}

function updateExtraField(question) {
	if (isIncomplete(question)) {
		if (isMarked(question)) {
			addIncompleteMarkedInputField(question);
		} else {
			addIncompleteUnmarkedInputField(question);
		}
	}	
}

function isIncomplete(question) {
	 var inputArray = question.getElementsByTagName('input');
	 for (var i=0; i<inputArray.length; i++) {
		  if (inputArray[i].getAttribute('mcat_checked') == 'checked') {
			 return false;
		  }	  
	 }
	 return true;
}

function addIncompleteUnmarkedInputField(question) {
	var itemNumber = question.getElementsByTagName('input')[1].name;
	var node = document.createElement('input');
	node.id = 'extra'.concat(itemNumber);
	node.type = 'hidden';
	node.name = itemNumber;
	node.value = 'null';
	question.appendChild(node);
}

function addIncompleteMarkedInputField(question) {
	 var itemNumber = question.getElementsByTagName('input')[1].name;
	 var node = document.createElement('input');
	 node.id = 'extra'.concat(itemNumber);
	 node.type = 'hidden';
	 node.name = itemNumber;
	 node.value = 'm';
	 question.appendChild(node); 
}

function strikeOut(obj) {
	obj.style.textDecoration = 'line-through';
	var image = obj.getElementsByTagName('img')[0];
	if (image !== undefined) {
		image.style.opacity = '0.3';
		image.style.filter  = 'alpha(opacity=30)';//IE 8 and earlier
	}
}

function unStrikeOut(obj) {
	obj.style.textDecoration = 'none';
	var image = obj.getElementsByTagName('img')[0];
	if (image !== undefined) {
		image.style.opacity = '1.0';
		image.style.filter = 'alpha(opacity=100)';//IE 8 and earlier
	}
}

function isStrikedOut(obj) {
	if (obj.style.textDecoration == 'line-through') {return true;}
	var image = obj.getElementsByTagName('img')[0];
	if (image !== undefined && (image.style.opacity == '0.3' || image.style.filter == 'alpha(opacity=30)')) {return true;}
	return false;
}

function updateStrikeOut(span) {
	if (span.parentNode.previousSibling.firstChild.getAttribute('mcat_checked') == 'unchecked') {
		if (isStrikedOut(span)) {
			unStrikeOut(span);
		} else {
			strikeOut(span);
		}
	}
}

function clickNextButton() {
    var markButton = document.getElementById('mark_button');
    var currentItem = markButton.getAttribute('currentItem');
    var fieldsetArray = document.getElementsByTagName('fieldset');
    var finalItem = fieldsetArray.length - 1;
    if (currentItem == fieldsetArray[finalItem].getElementsByTagName('input')[0].name) {
		if (document.getElementById('annotationChanged').value == 'TRUE') {
            var annotation = document.getElementById('passage').innerHTML;
            document.getElementById('annotation').value = annotation;
        }
        return;
    }
    for (var i = 0; i < fieldsetArray.length; i++) {
        if (fieldsetArray[i].getElementsByTagName('input')[0].name == currentItem) {
            var question = fieldsetArray[i + 1];
            break;
        }
    }
    focusForMarking(question);
    return false;
}

function clickPreviousButton() {
    var markButton = document.getElementById('mark_button');
    var currentItem = markButton.getAttribute('currentItem');
    var fieldsetArray = document.getElementsByTagName('fieldset');
    if (currentItem == fieldsetArray[0].getElementsByTagName('input')[0].name) {
		if (document.getElementById('annotationChanged').value == 'TRUE') {
            var annotation = document.getElementById('passage').innerHTML;
            document.getElementById('annotation').value = annotation;
        }
        return;
    }
    for (var i = 0; i < fieldsetArray.length; i++) {
        if (fieldsetArray[i].getElementsByTagName('input')[0].name == currentItem) {
            var question = fieldsetArray[i - 1];
            break;
        }
    }
    focusForMarking(question);
    return false;
}

function clickReviewButton() {
    if (document.getElementById('annotationChanged').value == 'TRUE') {
        var annotation = document.getElementById('passage').innerHTML;
        document.getElementById('annotation').value = annotation;
    }
    return;
}
