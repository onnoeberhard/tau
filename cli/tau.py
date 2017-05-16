import datetime
import sys
import threading
import time

import RPi.GPIO as GPIO
import pymysql
from termcolor import colored

import credentials

GPIO.setwarnings(False)
GPIO.setmode(GPIO.BCM)
for pin in range(2, 13):
    GPIO.setup(pin, GPIO.OUT)

GPIO.output(2, 1)

monitor = True
lights = False
half = False
zigzag = 0
clock = False
stopwatch = False
stopped = False
web = True


def p_web():
    while web:
        db = pymysql.connect("localhost", credentials.mysql_user, credentials.mysql_password, "db")
        cursor = db.cursor()
        cursor.execute("SELECT * FROM `stuff` WHERE `key` = 'lastlog'")
        lastlog = cursor.fetchall()[0][2]
        cursor.execute("SELECT * FROM `stuff` WHERE `key` = 'lights'")
        value = cursor.fetchall()[0][2]
        for i in range(10):
            GPIO.output(i + 3, int(value[i]))
        cursor.execute("SELECT * FROM `lightlog` WHERE `id` >= '" + str(lastlog) + "'")
        rows = cursor.fetchall()
        offset = 1
        # while offset < len(rows) and rows[offset][1] == rows[0][1]:
        #     offset += 1
        for i in range(len(rows) - offset):
            row = rows[i + offset]
            first = rows[i + offset - 1]
            print(row[1] + "> " + colored(row[2], "magenta" if
                  "{0:010b}".format(abs(int(first[2], 2) - int(row[2], 2))) == "1000000000" or
                  "{0:010b}".format(abs(int(first[2], 2) - int(row[2], 2))) == "0000010000" else "red" if
                  "{0:010b}".format(abs(int(first[2], 2) - int(row[2], 2))) == "0100000000" or
                  "{0:010b}".format(abs(int(first[2], 2) - int(row[2], 2))) == "0000001000" else "yellow" if
                  "{0:010b}".format(abs(int(first[2], 2) - int(row[2], 2))) == "0010000000" or
                  "{0:010b}".format(abs(int(first[2], 2) - int(row[2], 2))) == "0000000100" else "green" if
                  "{0:010b}".format(abs(int(first[2], 2) - int(row[2], 2))) == "0001000000" or
                  "{0:010b}".format(abs(int(first[2], 2) - int(row[2], 2))) == "0000000010" else "blue" if
                  "{0:010b}".format(abs(int(first[2], 2) - int(row[2], 2))) == "0000100000" or
                  "{0:010b}".format(abs(int(first[2], 2) - int(row[2], 2))) == "0000000001" else "white")
                  + ((" from " + row[3]) if row[3] != "" else ""))
        if len(rows) > 1:
            cursor.execute("UPDATE `stuff` SET `value` = '" + str(rows[len(rows) - 1][0]) + "' WHERE `key` = 'lastlog'")
            db.commit()
        for i in range(10):
            GPIO.output(i + 3, int(value[i]))
        db.close()
        time.sleep(.1)


t_web = threading.Thread(target=p_web)
t_web.setDaemon(True)
t_web.start()


def p_zigzag():
    t0 = time.time()
    out = "1000000000"
    for o in range(10):
        GPIO.output(o + 3, int(out[o]))
    drift = 1
    while zigzag > 0:
        t = time.time()
        if t - t0 >= .15:
            t0 = t
            _out = ""
            if out == "0000000001":
                if zigzag == 1:
                    _out = "1000000000"
                else:
                    drift = -drift
                    _out = "0100000000"
            else:
                for o in range(len(out) - 1):
                    if out[o] == "1":
                        _out += "01"
                    else:
                        _out += "0"
            out = _out
            if drift < 0:
                for o in range(len(out)):
                    GPIO.output(o + 3, int(out[len(out) - o - 1]))
            else:
                for o in range(10):
                    GPIO.output(o + 3, int(out[o]))
    for o in range(10):
        GPIO.output(o + 3, 0)


def p_clock():
    while clock:
        _hour = 0
        _time = datetime.datetime.now().time()
        for o in range(12):
            if _time.hour == o or _time.hour == o + 12:
                _hour = o
        _min = _time.minute
        binhour = format(_hour, '04b')
        binmin = format(_min, '06b')
        bintime = binhour + binmin
        for o in range(10):
            GPIO.output(o + 3, int(bintime[o]))
    for o in range(10):
        GPIO.output(o + 3, 0)


def p_stopwatch():
    start = time.time()
    while stopwatch and not stopped:
        a = format(time.time() - start, '.12f')
        if float(a) < 60:
            bintime = format(int(float(a)), '06b') + format(int((float(a) - int(float(a))) * 16), '04b')
        elif float(a) < 60 * 16:
            bintime = format(int(float(a) // 60), '04b') + format(int(float(a) - float(a) // 60 * 60), '06b')
        elif float(a) < 60 * 60 * 16:
            bintime = format(int(float(a) // 60 // 60), '04b') + format(
                int((float(a) - float(a) // 60 // 60 * 60 * 60) // 60), '06b')
        else:
            bintime = format(int(float(a) // 60 // 60), '010b')
        for o in range(10):
            GPIO.output(o + 3, int(bintime[o]))


while True:
    cmd = input()
    if cmd == "m":
        monitor = not monitor
        print("Turning monitor " + ("on." if monitor else "off."))
        GPIO.output(2, monitor)
    elif cmd == "w":
        web = not web
        half = False
        zigzag = 0
        clock = False
        stopwatch = False
        lights = False
        for i in range(10):
            GPIO.output(i + 3, 0)
        if web:
            t_web = threading.Thread(target=p_web)
            t_web.setDaemon(True)
            t_web.start()
        print(("Start" if web else "Stopp") + "ing server control.")
    elif cmd == "l":
        web = False
        half = False
        zigzag = 0
        clock = False
        stopwatch = False
        lights = not lights
        print("Turning lights " + ("on." if lights else "off."))
        for i in range(10):
            GPIO.output(i + 3, lights)
    elif cmd == "h":
        web = False
        lights = False
        zigzag = 0
        clock = False
        stopwatch = False
        half = not half
        print("Turning half the lights " + ("on." if half else "off."))
        sequence = "1010101010"
        for i in range(10):
            GPIO.output(i + 3, int(sequence[i]) if half else 0)
    elif cmd == "z":
        web = False
        lights = False
        half = False
        clock = False
        stopwatch = False
        zigzag = 1 if zigzag == 0 else 2 if zigzag == 1 else 0
        if zigzag == 1:
            t_zigzag = threading.Thread(target=p_zigzag)
            t_zigzag.setDaemon(True)
            t_zigzag.start()
        print("Running!" if zigzag == 1 else "Zig-Zagging!" if zigzag == 2 else "Stopping Zig-Zag.")
    elif cmd == "c":
        web = False
        lights = False
        half = False
        zigzag = 0
        stopwatch = False
        clock = not clock
        if clock:
            t_clock = threading.Thread(target=p_clock)
            t_clock.setDaemon(True)
            t_clock.start()
        print(("A" if clock else "Dea") + "ctivating clock.")
    elif cmd == "s":
        web = False
        lights = False
        half = False
        zigzag = 0
        clock = False
        if not stopwatch:
            stopped = False
        _sw = stopwatch
        _st = stopped
        stopwatch = not (stopwatch and stopped)
        stopped = stopwatch and not stopped and _sw == stopwatch
        if stopwatch and not stopped:
            t_stopwatch = threading.Thread(target=p_stopwatch)
            t_stopwatch.setDaemon(True)
            t_stopwatch.start()
        elif not stopwatch:
            for i in range(10):
                GPIO.output(i + 3, 0)
        if _sw != stopwatch:
            print(("Activating and star" if stopwatch else "Deactiva") + "ting stopwatch.")
        elif _st != stopped:
            print("Stopping stopwatch.")
    elif cmd == "exit":
        sys.exit(0)
